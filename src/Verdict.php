<?php

declare(strict_types=1);

namespace Kekkai;

enum Verdict: string
{
    case Allow = 'allow';
    case Ask = 'ask';
    case Deny = 'deny';
}
