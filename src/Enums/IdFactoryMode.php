<?php

namespace XWP\DI\Enums;

enum IdFactoryMode: string {
    case Deterministic = 'deterministic';
    case Random        = 'random';
}
