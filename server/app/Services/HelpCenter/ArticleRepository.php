<?php namespace App\Services\HelpCenter;

use DB;
use App\Article;
use App\ArticleFeedback;
use App\Services\TagRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ArticleRepository {

    /**
     * Article model instance.
     *
     * @var Article
     */
    private $article;

    /**
     * TagRepository instance.
     *
     * @var TagRepository
     */
    private $tagRepository;

    /**
     * Help center article feedback model instance.
     *
     * @var ArticleFeedback
     */
    private $feedback;

    /**
     * Create new ArticleRepository instance.
     *

     * @param Article $article
     * @param TagRepository $tagRepository
     * @param ArticleFeedback $feedback
     */
    public function __construct(Article $article, TagRepository $tagRepository, ArticleFeedback $feedback)
    {
        $this->article  = $article;
        $this->feedback = $feedback;
        $this->tagRepository = $tagRepository;
    }

    /**
     * Find article with specified ID.
     *
     * @throws ModelNotFoundException
     * @param integer $id
     *
     * @return Collection|\Illuminate\Database\Eloquent\Model
     */
    public function findOrFail($id)
    {
        $article = $this->article->with('categories.parent.children', 'tags')->findOrFail($id);

        return $article;
    }

    /**
     * Create a new article from specified params.
     *
     * @param array $params
     * @return Article
     */
    public function create($params)
    {
        $article = $this->article->create([
            'title'       => $params['title'],
            'body'        => $params['body'],
            'slug'        => isset($params['slug']) ? $params['slug'] : null,
            'description' => isset($params['description']) ? $params['description']: null,
            'draft'       => isset($params['draft']) ? $params['draft'] : 0,
            'extra_data'  => isset($params['extra_data']) ? $params['extra_data'] : null,
        ]);

        $article->categories()->attach($params['categories']);

        $this->handleTags($article, $params, 'store');

        return $article;
    }

    /**
     * Update specified article with given params.
     *
     * @throws ModelNotFoundException
     * @param integer $id
     * @param array $params
     *
     * @return Article
     */
    public function update($id, $params)
    {
        $article = $this->article->findOrFail($id);

        $article->fill([
            'title'       => $params['title'],
            'body'        => $params['body'],
            'slug'        => isset($params['slug']) ? $params['slug']: null,
            'description' => isset($params['description']) ? $params['description']: null,
            'draft'       => isset($params['draft']) ? $params['draft'] : 0,
            'position'    => isset($params['position']) ? $params['position'] : 0,
            'extra_data'  => isset($params['extra_data']) ? $params['extra_data'] : null,
        ]);

        $article->save();

        if (isset($params['categories'])) {
            $article->categories()->sync($params['categories']);
        }

        $this->handleTags($article, $params, 'update');

        return $article;
    }

    /**
     * Delete specified help center articles.
     *
     * @param integer[] $ids
     * @return int
     */
    public function deleteMultiple($ids)
    {
        //detach categories
        DB::table('category_article')->whereIn('article_id', $ids)->delete();

        //detach tags
        DB::table('taggables')->whereIn('taggable_id', $ids)->where('taggable_type', Article::class)->delete();
        
        //delete articles
        $this->article->whereIn('id', $ids)->delete();

        return count($ids);
    }

    /**
     * Paginate articles matching specified params.
     *
     * @param array $params
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginateArticles($params)
    {
        $paginator = $this->getIndexQuery($params)->paginate(isset($params['per_page']) ? $params['per_page'] : 15);

        $paginator->map(function($article) {
            $article['body'] = str_limit(strip_tags(html_entity_decode($article['body'])), 200);
            return $article;
        });

        return $paginator;
    }

    /**
     * Submit user feedback about specified article.
     *
     * @param array   $params
     * @return bool
     */
    public function submitFeedback($params)
    {
        //if we are not able to resolve user ip and user is not logged in, bail
        if ( ! $params['user_id'] && ! $params['ip']) return 0;

        $article = $this->article->findOrFail($params['article_id']);

        //if we have user_id, search for existing feedback by user_id
        if ($params['user_id']) {
            $feedback = $article->feedback()->where('user_id', $params['user_id'])->first();
        }

        //if we didn't find feedback by user_id and have client IP, search for existing feedback by client IP
        if ( ! isset($feedback) && $params['ip']) {
            $feedback = $article->feedback()->where('ip', $params['ip'])->first();
        }

        if ( ! $feedback) $feedback = $this->feedback->newInstance();

        return $feedback->fill($params)->save();
    }


    /**
     * Attach or Sync tags for article depending on http method.
     *
     * @param Article $article
     * @param array     $params
     * @param string    $type
     */
    private function handleTags(Article $article, $params, $type = 'store')
    {
        $method = $type === 'store' ? 'attach' : 'sync';

        if (isset($params['tags'])) {
            $tags = $this->tagRepository->getByNamesOrCreate($params['tags']);
            $article->tags()->$method($tags->pluck('id')->toArray());
        }
    }

    /**
     * Create eloquent query for fetching multiple articles from specified params.
     *
     * @param array $params
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function getIndexQuery($params)
    {
        $query = $this->article->with('categories.parent', 'tags');

        //filter by search query
        if (isset($params['query'])) {
            $query->where('title', 'like', $params['query'].'%');
        }

        //filter by categories
        if (isset($params['categories']) && $params['categories']) {
            $query->filterByCategories($params['categories']);
        }

        //filter by tags
        if (isset($params['tags']) && $params['tags']) {
            $query->filterByTags($params['tags']);
        }

        //filter by draft status
        if (isset($params['draft'])) {
            $query->where('draft', (int) $params['draft']);
        }

        //order
        if (isset($params['orderBy'])) {
            $order = explode('|', $params['orderBy']);
            $column = (isset($order[0]) && in_array($order[0], $this->article->orderFields)) ? $order[0] : 'views';
            $direction = isset($order[1]) ? $order[1] : 'desc';

            //order articles by the amount of 'was helpful' user
            //feedback they have in article_feedback table
            if ($order[0] === 'was_helpful') {
                $query->orderByFeedback($direction);
            }

            //do a regular order, by a column in main articles table
            else {
                $query->orderBy($column, $direction);
            }
        }

        return $query;
    }
}