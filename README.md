![PHP](https://img.shields.io/badge/PHP-8.2+-8892BF?style=flat&logo=php)
![No Composer](https://img.shields.io/badge/Zero-Composer-lightgrey)

# DunnoTheNameYet (Working Title)

A dependency-free PHP microframework that runs on:

- Native PHP 8.2+
- Caffeine
- Occasional Regret

This is a microframework without any dependency to Composer.

Purely using native PHP and some Headaches

**Warning:** May contain traces of:

- Spaghetti code
- "It works on my machine"
- `eval()` jokes that went too far(jk)
- Bunch of TODO comments

### Features

- ⚙️ Modular Architecture  
  Organized by `App`, `Foundation`, `Http`, and `Resources`, allowing scalable and clean development.

- 🧩 Component System  
  Reusable UI components powered by `.rx.php` view engine.

- 📡 Advanced Routing  
  Supports `Radix`, `Regex`, and `Trie` based routers via `Routers` interface.

- ⚒️ Built-in CLI Support  
  Command-based tooling for faster local development (`App\CLI`, `Foundation\CLI`).

- 🧠 Facade System  
  Laravel-inspired facades for `DB`, `Request`, `Response`, `Rx`, etc.

- 🔐 Security & Validation  
  Includes `CSRF`, `RateLimiter`, and `Validator` utilities.

- 💾 Database Abstraction  
  Custom `QueryBuilder`, `Model`, and `Migration` classes. SQLite by default.

- 🐞 Debugging Tools  
  Structured logging, error handling, and visual debug support via `Debug` module.

- ⚡ Reactive Engine  
  Custom lightweight reactive system under `Reactive\Reactive.php`.

- 📚 Helpers & Utilities  
  Rich set of PHP helpers (`Env`, `Unix`, `Facade`, `Utility`, etc).

- 💽 Session & Class Managers  
  Fine control over instance lifecycles and session data.

- 🧬 Middleware Support  
  Middleware pipelines for both `Web` and `API` traffic.

- 📁 Static File Server  
  Serve and handle media and static assets with `StaticFile.php`.

- 📜 Custom Compiler  
  Internal compiler under `Compiler\Rx.php` for parsing/rendering `.rx.php` views.

- 🧰 Type & Collection Utilities  
  Includes advanced `Collection` class and `Str` utilities.

- 🧠 Experimental Features  
  `_EXPE.php` files indicate experimental and bleeding-edge logic (e.g., `Model_EXPE`, `QueryBuilder_EXPE`).

- 🎨 Public Assets & Views  
  Ready-to-use CSS, JS, media, and component-based views (`resources/views/...`).

- 📂 Config & Router Plugins  
  Configurable via `config/*.php` and plugin-ready router system.

- 📈 Rate Limit Logs & Cache  
  Intelligent rate limiting with logging & file-based cache system.

---

# Attention!
Still on development(i guess?), so there is still no command for generating controller, view, model, etc... You can still create them by yourself though. There is maybe some bug that might I didn't know yet, you can help me mention it at issue... or not? well whatever

---

🧠 **Note:** Project still includes some experimental elements (like `swoole.so`), not yet functional. And some of Database related features may not yet fully working.
