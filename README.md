# Slimbot

Ultra-lightweight personal AI assistant written in PHP. Slimbot supports multiple LLM providers, tool calls, and a small web UI and server for chat sessions.

## Features

- Multi-provider LLM support: OpenAI, Google Gemini, Claude, and Ollama
- Tool calling for actions like file edits, web fetch/search, and memory management
- CLI chat, interactive mode, and a standalone server
- Lightweight web UI (public endpoints)
- Persistent sessions and workspace memory

## Requirements

- PHP >= 8.1
- Composer
- An LLM provider API key (unless using Ollama locally)

## Setup

1) Install dependencies:

```bash
composer install
```

2) Create your environment file:

```bash
cp .env.example .env
```

3) Configure provider settings in `.env`:

```dotenv
AI_PROVIDER=openai
AI_MODEL=
OPENAI_API_KEY=your_key_here
GOOGLE_API_KEY=
CLAUDE_API_KEY=
OLLAMA_HOST=http://localhost:11434
```

## Usage

### CLI (single message)

```bash
php index.php chat "Hello!"
```

### CLI (interactive session)

```bash
php index.php interactive
```

### Run the Agent server

```bash
php index.php server 8080
```

or:

```bash
php bin/slimbot-server 8080
```

### Web UI (optional)

Serve the `public` folder with a local PHP server:

```bash
php -S 127.0.0.1:8000 -t public
```

Then open:

- `http://127.0.0.1:8000/index.html`

The web UI expects the Agent server to be running on `127.0.0.1:8080`.

## Provider Notes

- OpenAI, Google, and Claude support tool calling and vision inputs.
- Ollama uses the OpenAI-compatible endpoint. Some Ollama models do not support tools; if you see a tools-related error, switch to a model that does (for example `llama3.1`).

## Project Layout

- `index.php` - CLI entry point
- `bin/slimbot-server` - Dedicated server entry point
- `public/` - Web UI and lightweight endpoints
- `src/` - Core agent, server, providers, and tools
- `workspace/` - Sessions, memory, uploads, and configuration docs

## Troubleshooting

- Missing API key errors: ensure `.env` contains the key for your selected `AI_PROVIDER`.
- Ollama 400 errors about tools: pick a tool-capable model or disable tools at the model level.

## License

MIT

