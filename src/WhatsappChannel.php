<?php

namespace NotificationChannels\HyperSender;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Response;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notification;
use NotificationChannels\HyperSender\Exceptions\CouldNotSendNotification;

/**
 * Class WhatsappChannel.
 */
class WhatsappChannel
{
    public function __construct(
        private readonly Dispatcher $dispatcher
    ) {}

    /**
     * Send the given notification.
     *
     *
     * @throws CouldNotSendNotification|\JsonException
     */
    public function send(mixed $notifiable, Notification $notification): ?array
    {
        // @phpstan-ignore-next-line
        $message = $notification->toWhatsapp($notifiable);

        if (is_string($message)) {
            $message = WhatsappMessage::create($message);
        }

        if (! $message->canSend()) {
            return null;
        }

        $to = $message->getPayloadValue('chatId')
            ?: ($notifiable->routeNotificationFor('whatsapp', $notification)
            ?: $notifiable->routeNotificationFor(self::class, $notification));

        if (! $to) {
            return null;
        }

        if ($message->hasToken()) {
            $message->whatsapp->setToken($message->token);
        }

        try {
            $response = $message->send();
        } catch (CouldNotSendNotification $exception) {
            $data = [
                'to' => $message->getPayloadValue('chatId'),
                'request' => $message->toArray(),
                'exception' => $exception,
            ];

            if ($message->exceptionHandler) {
                ($message->exceptionHandler)($data);
            }

            $this->dispatcher->dispatch(
                new NotificationFailed($notifiable, $notification, 'whatsapp', $data)
            );

            throw $exception;
        }

        if ($response instanceof Response) {
            return json_decode(
                $response->getBody()->getContents(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        return $response;
    }
}
