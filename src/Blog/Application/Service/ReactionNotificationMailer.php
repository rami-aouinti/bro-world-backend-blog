<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Application\Service\Interfaces\ReactionNotificationMailerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface as MailerTransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ReactionNotificationMailer implements ReactionNotificationMailerInterface
{
    public function __construct(
        private readonly UserProxy $userProxy,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws MailerTransportExceptionInterface
     */
    public function sendPostReactionNotificationEmail(string $postAuthorId, string $reactorId, ?string $postSlug): void
    {
        $this->sendReactionEmail(
            $postAuthorId,
            $reactorId,
            'Emails/post_reaction.html.twig',
            'Someone reacted to your post',
            $postSlug
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws MailerTransportExceptionInterface
     */
    public function sendCommentReactionNotificationEmail(string $commentAuthorId, string $reactorId, ?string $postSlug): void
    {
        $this->sendReactionEmail(
            $commentAuthorId,
            $reactorId,
            'Emails/comment_reaction.html.twig',
            'Someone reacted to your comment',
            $postSlug
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws MailerTransportExceptionInterface
     */
    private function sendReactionEmail(
        string $recipientId,
        string $reactorId,
        string $template,
        string $subject,
        ?string $postSlug
    ): void {
        if ($recipientId === $reactorId) {
            return;
        }

        $users = $this->userProxy->batchSearchUsers([$recipientId, $reactorId]);
        $recipient = $users[$recipientId] ?? null;
        $reactor = $users[$reactorId] ?? null;

        if ($recipient === null || $reactor === null || empty($recipient['email'])) {
            return;
        }

        $email = (new Email())
            ->from('admin@bro-world.de')
            ->to($recipient['email'])
            ->subject($subject)
            ->html(
                $this->twig->render($template, [
                    'recipient' => $recipient['firstName'] ?? $recipient['email'],
                    'reactor' => $reactor['firstName'] ?? $reactor['email'] ?? 'Someone',
                    'slug' => $postSlug,
                ])
            );

        $this->mailer->send($email);
    }
}
