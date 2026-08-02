<?php

namespace App\Enums;

enum MediaKind: string
{
    case Image = 'image';
    case Icon = 'icon';
    case Video = 'video';
    case Document = 'document';
}
