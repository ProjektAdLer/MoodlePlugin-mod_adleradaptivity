<?php

return new class extends phpunit_coverage_info {
    protected $includelistfolders = [
        'classes',
        'backup/moodle2'
    ];

    protected $includelistfiles = [
        'lib.php',
        'locallib.php',
        'renderer.php',
        'rsslib.php',
        'db/uninstall.php'
    ];

    protected $excludelistfolders = [
        'tests',
        'dev_utils'
    ];

    protected $excludelistfiles = [];
};
