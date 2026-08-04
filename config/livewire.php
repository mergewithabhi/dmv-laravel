<?php

return [
    'csp_safe' => true,
    'temporary_file_upload' => [
        'rules' => ['required', 'file', 'max:102400'],
        'preview_mimes' => ['png', 'gif', 'mp4', 'mov', 'jpg', 'jpeg', 'webp', 'webm'],
        'max_upload_time' => 10,
    ],
];
