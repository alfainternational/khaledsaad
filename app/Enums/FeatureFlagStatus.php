<?php

namespace App\Enums;

enum FeatureFlagStatus: string
{
    case Off = 'off';
    case Beta = 'beta';
    case On = 'on';
}
