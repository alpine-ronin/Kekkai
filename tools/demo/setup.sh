#!/bin/sh
# The demo repository of the README's pictures: a small DDD shop with two bounded contexts, Kekkai set up for Claude
# Code, and the JSON Claude Code sends to the edit hook when the agent edits an invoice.
#
#   tools/demo/setup.sh /tmp/shop      (kekkai on the PATH)

set -eu

shop=$1
mkdir -p "$shop/src/Catalog/Domain" "$shop/src/Billing/Domain" "$shop/tests/Catalog"
cd "$shop"
git init -q -b main
git config user.email demo@example.com
git config user.name demo

cat > src/Catalog/Domain/Product.php <<'PHP'
<?php

final class Product
{
    public function __construct(public readonly string $sku) {}
}
PHP
cat > src/Billing/Domain/Invoice.php <<'PHP'
<?php

final class Invoice
{
    public function __construct(public readonly int $total) {}
}
PHP
mv src/Billing/Domain/Invoice.php src/Billing/Invoice.php
rmdir src/Billing/Domain
echo '{}' > composer.lock

cat > kekkai.yaml <<'YAML'
zones:
  catalog: ['src/Catalog/', 'tests/Catalog/']
  billing: ['src/Billing/', 'tests/Billing/']

protected: [composer.json, composer.lock, 'migrations/']

without-scope: ask
outside-scope: deny
allow-widen: true

checks:
  - name: syntax
    run: echo "$KEKKAI_CHANGED_FILES" | grep '\.php$' | xargs -r -n1 php -l > /dev/null
YAML

kekkai install claude --write --binary kekkai > /dev/null
printf '{"tool_name": "Edit", "tool_input": {"file_path": "%s/src/Billing/Invoice.php"}}\n' "$shop" > edit.json
printf 'edit.json\n' > .git/info/exclude
git add -A
git commit -qm "Shop"
