<?php

namespace App\Enums;

enum FieldGroup: string
{
    case Heading = 'heading';
    case Text = 'text';
    case Banner = 'banner';
    case Gallery = 'gallery';
    case Icon = 'icon';
    case ButtonLabel = 'button_label';
    case ButtonLink = 'button_link';
    case Statistic = 'statistic';
    case RepeatedItem = 'repeated_item';

    public function label(): string
    {
        return match ($this) {
            self::Heading => 'Headings',
            self::Text => 'Text',
            self::Banner => 'Banners and images',
            self::Gallery => 'Gallery',
            self::Icon => 'Icons',
            self::ButtonLabel => 'Button text',
            self::ButtonLink => 'Button links',
            self::Statistic => 'Statistics',
            self::RepeatedItem => 'Repeated items',
        };
    }
}
