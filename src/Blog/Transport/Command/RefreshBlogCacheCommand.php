<?php

declare(strict_types=1);

namespace App\Blog\Transport\Command;

use App\Blog\Application\Service\Comment\CommentCacheService;
use App\Blog\Application\Service\Post\PostCachePayloadBuilder;
use App\Blog\Application\Service\Post\PostFeedCacheService;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_merge;
use function array_unique;
use function in_array;
use function sprintf;

#[AsCommand(name: 'blog:cache:refresh', description: 'Warm up the blog caches for public feeds and related resources.')]
final class RefreshBlogCacheCommand extends Command
{
    private const OPTION_SCOPE = 'scope';
    private const DEFAULT_SCOPE = 'all';
    private const SCOPES = ['posts', 'comments', 'reactions', 'all'];
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_LIMIT = 10;

    public function __construct(
        private readonly PostRepositoryInterface $postRepository,
        private readonly PostFeedCacheService $postFeedCacheService,
        private readonly CommentCacheService $commentCacheService,
        private readonly PostCachePayloadBuilder $postCachePayloadBuilder,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption(
                self::OPTION_SCOPE,
                null,
                InputOption::VALUE_REQUIRED,
                'Select which caches should be refreshed (posts, comments, reactions, all).',
                self::DEFAULT_SCOPE,
            );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $scope = (string)$input->getOption(self::OPTION_SCOPE);

        if (!in_array($scope, self::SCOPES, true)) {
            $io->error(sprintf('Invalid scope "%s". Allowed values: %s.', $scope, implode(', ', self::SCOPES)));

            return Command::INVALID;
        }

        $page = self::DEFAULT_PAGE;
        $limit = self::DEFAULT_LIMIT;

        $shouldWarmPosts = $scope === 'posts' || $scope === 'all';
        $shouldWarmComments = $scope === 'comments' || $scope === 'all';
        $shouldWarmReactions = $scope === 'reactions' || $scope === 'all';

        if ($shouldWarmPosts) {
            $this->postFeedCacheService->get(
                $page,
                $limit,
                fn () => $this->postCachePayloadBuilder->buildPostFeedPayload($page, $limit)
            );
        }

        $posts = $this->postRepository->findWithRelations($limit, 0);
        if ($posts === []) {
            $io->warning('No posts were found to warm cache entries.');

            return Command::SUCCESS;
        }

        foreach ($posts as $post) {
            \assert($post instanceof Post);
            $postId = $post->getId();

            if ($shouldWarmComments) {
                $commentsPayload = $this->commentCacheService->getPostComments(
                    $postId,
                    $page,
                    $limit,
                    fn () => $this->postCachePayloadBuilder->buildPostCommentsPayload($postId, $page, $limit)
                );

                if ($shouldWarmReactions) {
                    $this->warmCommentCachesForPost($commentsPayload['comments'] ?? []);
                }
            }

            if ($shouldWarmReactions) {
                $this->commentCacheService->getPostLikes(
                    $postId,
                    fn () => $this->postCachePayloadBuilder->buildPostLikesPayload($postId, $post)
                );

                $this->commentCacheService->getPostReactions(
                    $postId,
                    fn () => $this->postCachePayloadBuilder->buildPostReactionsPayload($postId, $post)
                );
            }
        }

        $io->success(sprintf('Blog caches refreshed for scope "%s".', $scope));

        return Command::SUCCESS;
    }

    /**
     * @param array<int, array<string, mixed>> $cachedComments
     */
    private function warmCommentCachesForPost(array $cachedComments): void
    {
        $commentIds = $this->collectCommentIds($cachedComments);
        if ($commentIds === []) {
            return;
        }

        foreach ($commentIds as $commentId) {
            $this->commentCacheService->getCommentLikes(
                $commentId,
                fn () => $this->postCachePayloadBuilder->buildCommentLikesPayload($commentId)
            );

            $this->commentCacheService->getCommentReactions(
                $commentId,
                fn () => $this->postCachePayloadBuilder->buildCommentReactionsPayload($commentId)
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $comments
     * @return string[]
     */
    private function collectCommentIds(array $comments): array
    {
        $ids = [];

        foreach ($comments as $comment) {
            if (!isset($comment['id'])) {
                continue;
            }

            $ids[] = (string)$comment['id'];
            $children = $comment['children'] ?? [];
            if ($children !== []) {
                $ids = array_merge($ids, $this->collectCommentIds($children));
            }
        }

        return array_unique($ids);
    }
}
