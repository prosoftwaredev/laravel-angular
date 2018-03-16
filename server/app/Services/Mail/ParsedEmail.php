<?php namespace App\Services\Mail;

use App;
use App\Services\Settings;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use EmailReplyParser\Parser\EmailParser;
use Illuminate\Contracts\Support\Jsonable;
use App\Services\Mail\Transformers\MailTransformer;

class ParsedEmail implements Jsonable
{
    /**
     * Raw email data.
     *
     * @var array
     */
    private $email;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var EmailBodyParser
     */
    private $emailBodyParser;

    /**
     * ParsedEmail constructor.
     *
     * @param Settings $settings
     * @param EmailBodyParser $emailBodyParser
     */
    public function __construct(Settings $settings, EmailBodyParser $emailBodyParser)
    {
        $this->settings = $settings;
        $this->emailBodyParser = $emailBodyParser;
    }

    /**
     * Set email data that should be parsed.
     *
     * @param array $data
     * @return static
     */
    public function setEmailData($data)
    {
        $this->email = $this->getMailTransformer()->transform($data);
        return $this;
    }

    /**
     * Return normalized email body.
     *
     * This will strip quoted replies from email and
     * remove any not allowed html tags.
     *
     * @param array $cidMap
     * @return string
     */
    public function getNormalizedBody($cidMap = [])
    {
        //remove quoted text from email, if not already removed
        if ( ! $this->hasBody('stripped-html')) {
            $body = $this->getBody('html') ?: $this->getBody('plain');
            $this->email['body']['stripped-html'] = (new EmailParser())->parse($body)->getVisibleText();
        }

        $body = $this->getBody('stripped-html');

        //replace CIDs in img src with actual image urls
        foreach($cidMap as $cid => $url) {
            $body = str_replace("cid:$cid", $url, $body);
        }

        return $this->emailBodyParser->parse($body);
    }


    /**
     * Get email subject.
     *
     * @return string
     */
    public function getSubject()
    {
        return $this->getHeader('Subject') ?: '(no subject)';
    }

    /**
     * Get email address of sender.
     *
     * @throws InvalidArgumentException
     * @return string
     */
    public function getSenderEmail()
    {
        $header = $this->getHeader('Reply-To') ?: $this->getHeader('From');
        preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b/i', $header, $match);

        if (isset($match[0])) return $match[0];

        throw new InvalidArgumentException("Could not extract email address from [$header]");
    }

    /**
     * Get email header by specified name.
     *
     * @param string $name
     * @return string
     */
    public function getHeader($name)
    {
        return Arr::get($this->email, "headers.$name");
    }

    /**
     * Check if email has specified header.
     *
     * @param string $name
     * @return bool
     */
    public function hasHeader($name)
    {
        return Arr::has($this->email, "headers.$name");
    }

    /**
     * Get all email headers.
     *
     * @return array
     */
    public function getHeaders()
    {
        return Arr::get($this->email, 'headers', []);
    }

    /**
     * Get email body by type (plain, html, stripped-html)
     *
     * @param string $type
     * @return string
     */
    public function getBody($type)
    {
        return Arr::get($this->email, "body.$type");
    }

    /**
     * Check if email has body of specified type.
     *
     * @param string $type
     * @return bool
     */
    public function hasBody($type)
    {
        return Arr::has($this->email, "body.$type");
    }

    /**
     * Get email attachments.
     *
     * @param string $type
     * @return Collection
     */
    public function getAttachments($type)
    {
        $attachments = Arr::get($this->email, 'attachments', []);

        //if attachment has a CID then it's inline, otherwise it's 'regular'
        return collect($attachments)->filter(function($attachment) use($type) {
            return $type === 'inline' ? $attachment['cid'] : ! $attachment['cid'];
        });
    }

    /**
     * Convert the parsed email to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode([
            'headers' => $this->getHeaders(),
            'body' => [
                'plain' => $this->getBody('plain'),
                'html' => $this->getBody('html'),
            ]
        ], $options);
    }

    /**
     * Get an instance of mail.handler.
     *
     * @throws InvalidArgumentException
     * @return MailTransformer
     */
    private function getMailTransformer()
    {
        $name = App::make(Settings::class)->get('mail.handler') ?: 'null';

        $class = 'App\Services\Mail\Transformers\\'.Str::studly($name).'MailTransformer';

        if (class_exists($class)) {
            return App::make($class);
        }

        throw new InvalidArgumentException("Mail Transformer [$name] not supported.");
    }

}