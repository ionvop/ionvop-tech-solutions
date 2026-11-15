# ionvop Tech Services

ionvop Tech Services builds custom software and websites for local businesses. This Laravel application includes a public services and portfolio site, a project inquiry chat, and a private admin dashboard for managing conversations and project details.

## Features

- Public home page with services, pricing, portfolio, and contact options.
- Visitor chat for project inquiries, with file attachments and voice notes.
- Admin dashboard with Google sign-in, conversation management, and project, payment, and contract details.
- Optional AI-assisted replies and conversation summaries through OpenRouter.

## Requirements

- PHP 8.3 or later and Composer.
- Node.js 20.19 or later, or 22.12 or later, and npm.
- SQLite for the default local database.

## Local Setup

From the project directory, run:

```bash
composer run setup
```

The setup script installs PHP and JavaScript dependencies, creates `.env` if needed, generates the application key, runs database migrations, and builds the frontend assets. The default configuration uses SQLite at `database/database.sqlite`.

Start the local development processes with:

```bash
composer run dev
```

## Configuration

The generated `.env` is suitable for basic local development. Set `APP_URL` to the URL where the application runs. The following integrations are optional:

- **AI chat and summaries:** set `OPENROUTER_API_KEY`. The default model is configured by `OPENROUTER_MODEL`.
- **Admin Google sign-in:** set `ADMIN_EMAIL` to the allowed admin account and configure `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI` with matching Google OAuth credentials and callback URL.

Without these credentials, the public site can run locally, but the related AI and Google sign-in features will not be available.

## Development and Tests

Build or watch the frontend independently with:

```bash
npm run build
npm run dev
```

Run the PHP and JavaScript tests with:

```bash
composer run test
npm test
```

## Agentic Development

Laravel Boost is included as a Composer development dependency and is installed by `composer run setup`. To initialize Boost for GitHub Copilot and add the DaisyUI skill, run these commands from the project directory:

```bash
php artisan boost:install
npx skills add saadeghi/daisyui --agent github-copilot --yes
```

Boost configuration is stored in `boost.json`. The DaisyUI skill is tracked in `skills-lock.json` and is useful when working with the app's Tailwind CSS and DaisyUI interface.
