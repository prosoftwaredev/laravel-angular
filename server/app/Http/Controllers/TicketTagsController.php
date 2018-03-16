<?php namespace App\Http\Controllers;

use App\Ticket;
use Illuminate\Http\Request;
use App\Services\TagRepository;
use App\Services\Ticketing\TicketRepository;

class TicketTagsController extends Controller
{
    /**
     * TicketRepository model instance.
     *
     * @var TicketRepository
     */
    private $tickets;

    /**
     * Laravel request instance.
     *
     * @var Request
     */
    private $request;

    /**
     * @var TagRepository
     */
    private $tags;

    /**
     * TicketTagsController constructor.
     *
     * @param TicketRepository $tickets
     * @param Request $request
     * @param TagRepository $tags
     */
    public function __construct(TicketRepository $tickets, Request $request, TagRepository $tags)
    {
        $this->tags    = $tags;
        $this->tickets = $tickets;
        $this->request = $request;
    }

    /**
     * Attach tag to specified tickets
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function add()
    {
        $this->authorize('update', Ticket::class);

        $this->validate($this->request, [
            'ids' => 'required|array',
            'tag' => 'required|string|max:255'
        ]);

        $tag = $this->tags->getByNamesOrCreate([$this->request->get('tag')])[0];

        $this->tickets->addTagToTickets(
            $this->request->input('ids'),
            $tag->id
        );

        return $this->success(['data' => $tag]);
    }

    /**
     * Remove tag from specified tickets.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function remove()
    {
        $this->authorize('update', Ticket::class);

        $this->validate($this->request, [
            'ids' => 'required|array',
            'tag' => 'required|integer'
        ]);

        $this->tickets->removeTagFromTickets(
            $this->request->input('ids'),
            $this->request->input('tag')
        );

        return $this->success();
    }
}
