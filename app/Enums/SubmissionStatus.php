<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Spam = 'spam';
    case Archived = 'archived';
}
