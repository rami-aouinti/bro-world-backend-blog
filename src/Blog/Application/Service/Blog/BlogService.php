<?php

declare(strict_types=1);

namespace App\Blog\Application\Service\Blog;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Repository\Interfaces\BlogRepositoryInterface;
use Bro\WorldCoreBundle\Infrastructure\ValueObject\SymfonyUser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * @package App\Blog\Application\Service\Blog
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
readonly class BlogService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BlogRepositoryInterface $blogRepository,
        private SluggerInterface $slugger,
        private string $logoDirectory
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws TransactionRequiredException
     * @throws NotSupported
     */
    public function getBlog(Request $request, SymfonyUser $symfonyUser): Blog
    {
        $response = $request->request->all();

        if (isset($response['blog'])) {
            $blogObject = $this->blogRepository->find($response['blog']);
        } else {
            $blogObject = $this->blogRepository->findOneBy([
                'title' => 'public',
            ]);

            if (!$blogObject) {
                $blogObject = new Blog();
                $blogObject->setTitle('public');
                $blogObject->setBlogSubtitle('General posts');
                $blogObject->setSlug('public');
                $blogObject->setAuthor(Uuid::fromString($symfonyUser->getId()));
                $blogObject->setColor('primary');
                $this->entityManager->persist($blogObject);
                $this->entityManager->flush();
            }
        }

        return $blogObject;
    }

    public function executeUploadLogoCommand(Request $request): string|JsonResponse
    {
        $files = $request->files->get('files');
        $file = $files[0];
        if (!$file) {
            return new JsonResponse([
                'error' => 'No file uploaded.',
            ], 400);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $file->guessExtension();

        try {
            $file->move(
                $this->logoDirectory,
                $newFilename
            );
        } catch (FileException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 500);
        }
        $baseUrl = $request->getSchemeAndHttpHost();
        $relativePath = '/uploads/logo/' . $newFilename;

        return $baseUrl . $relativePath;
    }
}
