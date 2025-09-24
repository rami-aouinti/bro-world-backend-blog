<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Application\Service\Interfaces\CommentNotificationMailerInterface;
use App\Blog\Domain\Entity\Comment;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @package App\User\User\Application\Service
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */

class CommentNotificationMailer implements CommentNotificationMailerInterface
{
    private MailerInterface $mailer;
    private Environment $twig;

    public function __construct(
        private readonly UserProxy $userProxy,
        MailerInterface $mailer,
        Environment $twig
    ) {
        $this->mailer = $mailer;
        $this->twig = $twig;
    }

    /**
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws LoaderError
     * @throws RedirectionExceptionInterface
     * @throws RuntimeError
     * @throws ServerExceptionInterface
     * @throws SyntaxError
     * @throws TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function sendCommentNotificationEmail(string $userId, string $commentAuthorId, string $slug): void
    {
        $usersById = $this->getUsersById();
        $user = $usersById[$userId] ?? null;
        $commentAuthor = $usersById[$commentAuthorId] ?? null;

        if ($user === null || $commentAuthor === null) {
            return;
        }

        $email = (new Email())
            ->from('admin@bro-world.de')
            ->to($user['email'])
            ->subject('Email Verification')
            ->html(
                $this->twig->render('Emails/comment.html.twig', [
                    'user' => $user['firstName'],
                    'commentAuthor' => $commentAuthor['firstName'],
                    'slug' => $slug,
                ])
            );

        $this->mailer->send($email);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws LoaderError
     * @throws RedirectionExceptionInterface
     * @throws RuntimeError
     * @throws ServerExceptionInterface
     * @throws SyntaxError
     * @throws TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function sendCommentReplyNotificationEmail(
        string $commentOwnerId,
        string $replyAuthorId,
        Comment $reply
    ): void {
        $slug = $this->resolveSlug($reply);
        if ($slug === null) {
            return;
        }

        $usersById = $this->getUsersById();
        $commentOwner = $usersById[$commentOwnerId] ?? null;
        $replyAuthor = $usersById[$replyAuthorId] ?? null;

        if ($commentOwner === null || $replyAuthor === null) {
            return;
        }

        $email = (new Email())
            ->from('admin@bro-world.de')
            ->to($commentOwner['email'])
            ->subject('New reply to your comment')
            ->html(
                $this->twig->render(
                    'Emails/comment_reply.html.twig',
                    [
                        'user' => $commentOwner['firstName'],
                        'commentAuthor' => $replyAuthor['firstName'],
                        'slug' => $slug,
                        'comment' => $reply->getContent(),
                    ]
                )
            );

        $this->mailer->send($email);
    }

    /**
     * @return array<string, array<string, mixed>>
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function getUsersById(): array
    {
        $users = $this->userProxy->getUsers();
        $usersById = [];

        foreach ($users as $user) {
            if (isset($user['id'])) {
                $usersById[$user['id']] = $user;
            }
        }

        return $usersById;
    }

    private function resolveSlug(Comment $comment): ?string
    {
        $current = $comment;

        while ($current !== null) {
            $post = $current->getPost();
            if ($post !== null && $post->getSlug() !== null) {
                return $post->getSlug();
            }

            $current = $current->getParent();
        }

        return null;
    }
}
