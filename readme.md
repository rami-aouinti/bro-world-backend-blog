# Bro World Backend

## Table of Contents
1. [Project Overview](#project-overview)
2. [Quick Start](#quick-start)
3. [Architecture at a Glance](#architecture-at-a-glance)
4. [Tech Stack](#tech-stack)
5. [Environment Configuration](#environment-configuration)
6. [Key Services & Tooling](#key-services--tooling)
7. [Running Tests & Quality Gates](#running-tests--quality-gates)
8. [API Usage](#api-usage)
9. [Configuration Reference](#configuration-reference)
10. [Deployment Considerations](#deployment-considerations)
11. [Contribution Guidelines](#contribution-guidelines)
12. [Troubleshooting & Support](#troubleshooting--support)

## Project Overview
Bro World Backend powers the Bro World blog and community experience. It exposes a JSON REST API built with Symfony 7 that covers blog creation and moderation, post publishing, audience engagement (likes, comments, reactions), and operational insights such as per-month statistics. The service follows a layered domain-driven design where transport controllers broker traffic to application resources and domain repositories, keeping business logic isolated from framework concerns.

Key capabilities include:
- CRUD endpoints for managing blogs and posts, backed by DTO-driven validation and reusable REST action traits.
- Interaction workflows for visitors through comment and like resources, complete with messaging pipelines for notifications and search indexing.
- Cached statistics endpoints that aggregate activity across the platform and serve low-latency analytics to authenticated consumers.
- Automated background processing (messenger workers, cron jobs) to handle long-running tasks without blocking API requests.

## Quick Start
1. **Install prerequisites**: Docker, Docker Compose, and GNU Make must be available on your workstation.
2. **Clone the repository** and copy the sample environment file:
   ```bash
   git clone git@github.com:your-org/bro-world-backend-blog.git
   cd bro-world-backend-blog
   cp .env .env.local
   ```
3. **Adjust environment overrides** (database credentials, mailers, JWT passphrases) inside `.env.local` or create dedicated `.env.staging` / `.env.prod` files.
4. **Build and start the development stack**:
   ```bash
   make build
   make start
   ```
5. **Initialize application dependencies**:
   ```bash
   make composer-install
   make migrate
   make messenger-setup-transports
   make create-roles-groups
   make generate-jwt-keys
   ```
6. **Verify the installation** by opening http://localhost/api/doc to inspect the generated Swagger UI, or run `make phpunit` to confirm the suite executes successfully.
7. **Stop services** with `make stop` and remove containers/volumes when necessary using `make down`.

Refer to `make help` for a discoverable list of available automation commands.

## Architecture at a Glance
- **Transport layer**: Symfony controllers, HTTP middleware, and event subscribers expose REST endpoints and orchestrate request lifecycles.
- **Application layer**: Services and resources perform orchestration tasks, mapping inputs to domain actions while handling validation via DTOs and Symfony forms/constraints.
- **Domain layer**: Rich entities, aggregates, and domain events capture the core business rules for blogs, posts, comments, likes, and statistics.
- **Infrastructure layer**: Doctrine repositories, message handlers, and adapters integrate with persistence, cache, queue, and search services.
- **Cross-cutting concerns**: Messenger transports, asynchronous workers, and cron jobs (configured through `migrations/` and `config/packages/messenger`) maintain system health and throughput.

Consult the `docs/` directory for deep dives into development workflow, messaging, API schema management, and IDE integration tips.

## Tech Stack
The application ships as a containerized environment orchestrated with Docker Compose. Major components are:
- **Symfony 7 + PHP 8.4 FPM** for the API runtime and background workers.
- **Nginx** as the HTTP entry point.
- **MySQL 8** for relational persistence.
- **Redis** for caching, locks, and queues.
- **RabbitMQ 4** for asynchronous messaging.
- **Elasticsearch 7 + Kibana** for search indexing and observability dashboards.
- **Mailpit** for capturing outbound email in development.

Supporting tools include PHPUnit, Easy Coding Standard, PHPStan, PHP Insights, Rector, PhpMetrics, PhpMD, PhpCPD, Composer QA utilities, and Qodana configuration for deeper analysis.

## Environment Configuration
The `compose.yaml`, `compose-staging.yaml`, `compose-prod.yaml`, and `compose-test-ci.yaml` files define isolated stacks for local development, staging mirroring, production-ready simulations, and CI/testing respectively. The Makefile wraps the Docker orchestration with environment-specific targets:

| Stage | Build | Start | Stop | Tear Down |
| --- | --- | --- | --- | --- |
| Development | `make build` | `make start` | `make stop` | `make down` |
| Testing/CI | `make build-test` | `make start-test` | `make stop-test` | `make down-test` |
| Staging | `make build-staging` | `make start-staging` | `make stop-staging` | `make down-staging` |
| Production | `make build-prod` | `make start-prod` | `make stop-prod` | `make down-prod` |

Other helpful targets:
- `make generate-jwt-keys` to provision JWT key pairs for authentication.
- `make messenger-setup-transports`, `make create-roles-groups`, `make migrate`, and `make migrate-cron-jobs` to prepare databases, background jobs, and security ACLs.
- `make ssh`, `make ssh-nginx`, `make ssh-mysql`, etc. to open shells inside running containers.
- `make logs-*` to stream service logs from the host.

## Key Services & Tooling
### Local Services
Once the development stack is running you can reach supporting UIs at:
- Swagger UI: http://localhost/api/doc
- RabbitMQ management: http://localhost:15672
- Kibana: http://localhost:5601
- Mailpit: http://localhost:8025

### Monitoring & Diagnostics
- Doctrine profiling and Symfony debug toolbar are enabled in the local environment for rapid iteration.
- Logs from PHP-FPM, Nginx, MySQL, and worker containers can be tailed with `make logs-<service>`.
- Kibana dashboards surface indexed search data and application logs through the ELK stack.

## Running Tests & Quality Gates
Execute the full PHPUnit suite from the host with:
```bash
make phpunit
```

Supplementary quality tooling is available through dedicated targets:
- Static analysis: `make phpstan`
- Coding standards: `make ecs` (fixable violations via `make ecs-fix`) and `make phpcs`
- Architecture metrics: `make phpmetrics`
- Code smells: `make phpmd`
- Duplicate detection: `make phpcpd` / `make phpcpd-html-report`
- Dependency hygiene: `make composer-normalize`, `make composer-validate`, `make composer-unused`, `make composer-require-checker`
- Holistic insights: `make phpinsights`

You can combine targets (for example `make qa`) to run curated bundles of quality gates prior to opening a pull request.

## API Usage
All API routes are served beneath `/api` with versioned prefixes. Highlights include:
- `/api/v1/blog` for blog administration (create, update, patch, list, fetch by id, id collection, counts).
- `/api/v1/post` for post lifecycle management with similar CRUD semantics.
- `/api/v1/statistics` for cached per-month aggregates of posts, blogs, likes, and comments.

The platform uses JWT bearer tokens. Generate keys via `make generate-jwt-keys`, configure issuers/clients to request tokens, and send authenticated requests with the `Authorization: Bearer <token>` header. Anonymous access is limited to explicitly whitelisted public routes.

OpenAPI documentation is generated through NelmioApiDocBundle and exposed in the Swagger UI, making it simple to explore payload schemas, available query parameters, and authentication requirements.

## Configuration Reference
### Environment Variables
The table below captures frequent overrides you may want to adjust for each environment. Defaults originate in `.env` and can be overridden by `.env.local`, `.env.staging`, or `.env.prod`.

| Variable | Purpose |
| --- | --- |
| `APP_ENV` | Chooses the Symfony runtime environment (`dev`, `test`, `prod`). |
| `APP_DEBUG` | Enables debug mode and verbose error output in non-production environments. |
| `DATABASE_URL` | Defines the DSN for the MySQL instance used by Doctrine ORM. |
| `MESSENGER_TRANSPORT_DSN` | Configures the default RabbitMQ transport for asynchronous messages. |
| `REDIS_URL` | Points to the Redis cache store for sessions, locks, and cache pools. |
| `ELASTICSEARCH_HOST` | Hostname for the Elasticsearch node used by the search subsystem. |
| `MAILER_DSN` | Controls outbound mail delivery (Mailpit in development). |
| `JWT_PASSPHRASE` | Passphrase used to protect generated JWT private keys. |

### Database & Migrations
- Run `make migrate` (or `make migrate-no-test` in production scenarios) after modifying Doctrine entities or schema mappings.
- Seed reference data by adding migrations or custom fixtures. Doctrine Fixtures Bundle can be enabled if richer data bootstrapping is required.
- Scheduled jobs can be registered through the cron job migrations located in `migrations/`.

### Assets & Frontend Integrations
- Asset building is orchestrated through Symfony AssetMapper and the `assets/` directory. Use `make asset-install` and `make asset-dev-server` for live reload workflows.
- Frontend consumers should rely on the documented REST endpoints and, where applicable, any HAL/JSON:API conventions outlined in the API specification.

## Deployment Considerations
- Prepare environment-specific overrides in `.env.prod` or `.env.staging` for secrets, database endpoints, queues, and cache backends.
- Use `make env-prod` or `make env-staging` to compile cached Symfony configuration (`.env.local.php`).
- Build immutable images with `make build-prod` (or staging equivalent) before pushing to registries; then orchestrate with the matching Compose file or translate settings into your target infrastructure (Kubernetes, ECS, etc.).
- Initialize production data stores with the migration and setup targets (`make migrate-no-test`, `make messenger-setup-transports`, `make create-roles-groups`, etc.).
- Monitor asynchronous workloads (messenger consumers) by running the Supervisord container or provisioning equivalent workers in your platform.
- Review Elasticsearch license options and adjust `docker/elasticsearch/config/elasticsearch.yml` if you need trial-only features before shipping.

## Contribution Guidelines
- Follow PSR-12 and Symfony best practices, applying strict types and rich domain models.
- Keep transport (controllers, subscribers, handlers), application (resources, services), infrastructure (repositories), and domain (entities, messages) layers decoupled and testable.
- Accompany features with application, integration, and unit tests. Target automation coverage before opening pull requests.
- Run `make ecs`, `make phpstan`, and `make phpunit` locally to catch regressions early, then use additional QA targets as needed.
- Document non-trivial workflows or architectural decisions in the `docs/` directory and update the Swagger schema for new endpoints.
- Follow the conventional Git workflow: branch from `main`, rebase frequently, and provide detailed pull request descriptions summarizing business impact and testing evidence.

## Troubleshooting & Support
- **Containers fail to start**: Run `make logs` or `docker compose logs` to inspect startup failures. Confirm ports in use do not conflict with services already running on the host.
- **Database migrations fail**: Verify MySQL readiness (`make ssh-mysql`) and ensure credentials match `DATABASE_URL`. Re-run migrations after clearing the cache with `make cache-clear` if Doctrine metadata changed.
- **JWT issues**: Delete old keys (`rm -rf config/jwt/*`) and rerun `make generate-jwt-keys`, confirming the passphrase matches your environment variables.
- **Worker backlog**: Run `make messenger-consume` locally or scale worker containers in staging/production to handle message spikes.
- **Elasticsearch connectivity**: Ensure the cluster is running (`make logs-elasticsearch`) and that index templates in `docker/elasticsearch/config` match the expected version.

For deeper dives, see the topic guides in `docs/` (development workflow, testing, Postman collections, messenger usage, Swagger, IDE integration) and leverage `make help` to inspect the automation surface area. Questions and enhancements can be proposed through Git issues or the team's communication channels.
