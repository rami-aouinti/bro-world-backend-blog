<?php

declare(strict_types=1);

namespace App\Blog\Application\Service\Interfaces;

/**
 *
 */
interface CommentNotificationMailerInterface
{
    /**
     * Loads the user for the given username.
     *
     * This method must throw UsernameNotFoundException if the user is not found.
     *
     * Method is override for performance reasons see link below.
     *
     * @see http://symfony2-document.readthedocs.org/en/latest/cookbook/security/entity_provider.html
     *      #managing-roles-in-the-database
     */
    public function sendCommentNotificationEmail(string $userId, string $commentAuthorId, string $slug):void;
}
