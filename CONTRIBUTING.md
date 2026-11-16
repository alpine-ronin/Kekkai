# Contributing

```bash
git clone https://github.com/alpine-ronin/kekkai && cd kekkai
composer install
composer check   # code style, PHPStan at level max, tests
```

- Read [docs/SPEC.md](docs/SPEC.md) first: a change of behaviour changes the spec, in the same pull request.
- A new rule of the gate or a new finding of the review comes with its row in the spec's tables and a test.
- PHP code carries no inline comments; PHPDoc only declares types (`@param`, `@return`, `@var`).
- Messages written for the agent say what happened and what to do next, in one or two sentences.
- `composer cs:fix` fixes the code style.
