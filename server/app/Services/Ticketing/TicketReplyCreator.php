<?php namespace App\Services\Ticketing;

use Mail;
use App\User;
use App\Reply;
use App\Ticket;
use Carbon\Carbon;
use App\Mail\TicketReply;
use App\Services\Settings;
use App\Events\TicketUpdated;
use App\Events\TicketReplyCreated;

class TicketReplyCreator
{
    /**
     * @var TicketRepository
     */
    private $ticketRepository;

    /**
     * @var ReplyRepository
     */
    private $replyRepository;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * TicketReplyCreator constructor.
     *
     * @param TicketRepository $ticketRepository
     * @param ReplyRepository $replyRepository
     * @param Settings $settings
     */
    public function __construct(TicketRepository $ticketRepository, ReplyRepository $replyRepository, Settings $settings)
    {
        $this->settings = $settings;
        $this->replyRepository = $replyRepository;
        $this->ticketRepository = $ticketRepository;
    }

    /**
     * Create a new reply for specified ticket.
     *
     * @param Ticket $ticket
     * @param array $data
     * @param string $type
     * @param User $creator
     *
     * @return Reply
     */
    public function create(Ticket $ticket, $data, $type, User $creator = null)
    {
        $reply = $this->replyRepository->create($data, $ticket, $type);

        if ($type === 'replies') {
            $statusName = isset($data['status']) ? $data['status']['name'] : 'open';

            $ticket = $this->handleTimestamps($ticket, $creator, $statusName);

            //change ticket status to specified one or "open"
            $this->ticketRepository->changeStatus([$ticket->id], $statusName);

            //send reply via email
            if ($this->settings->get('replies.send_email') && ($creator && $creator->isAgent())) {
                Mail::send(new TicketReply($ticket, $reply));
            }
        }

        event(new TicketUpdated($ticket));
        event(new TicketReplyCreated($ticket, $reply));

        return $reply->load('user', 'uploads');
    }

    /**
     * Set ticket updated_at, closed_at and closed_by properties.
     *
     * @param Ticket $ticket
     * @param User|null $creator
     * @param string $statusName
     * @return Ticket
     */
    private function handleTimestamps(Ticket $ticket, $creator = null, $statusName)
    {
        //touch tickets updated_at property
        $ticket->updated_at = Carbon::now();

        //set closed_at property, if ticket is closed
        if ($statusName === 'closed') {
            $ticket->closed_at = Carbon::now();
            $ticket->closed_by = ($creator ? $creator->id : null);
        } else {
            $ticket->closed_at = null;
            $ticket->closed_by = null;
        }

        $ticket->save();

        return $ticket;
    }
}