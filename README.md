# Import WP Multiple Files Addon

Requires Import WP: 2.6.2

**Version: 0.0.1**

## Description

Import WP Multiple Files Addon allows you to import data from multiple files.

### How to use

Add config file to local filesource directory, filename should match importer parser xml or csv e.g. datafeed.xml.php,

```
<?php

/**
 * Import WP Datafeed config
 */
return [
    'pattern' => '*.xml',
    'order' => 'ASC',
    'orderby' => 'filemtime',
    'destination' => 'processed'
];

```
