<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Application\Service\Interfaces\CommentNotificationMailerInterface;
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
        private UserProxy $userProxy,
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
        $users = $this->userProxy->getUsers();
        $usersById = [];

        foreach ($users as $user) {
            $usersById[$user['id']] = $user;
        }
        $user = $usersById[$userId];
        $commentAuthor = $usersById[$commentAuthorId];

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
}
