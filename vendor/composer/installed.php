<?php return array(
    'root' => array(
        'name' => '__root__',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => 'c5b7bc6035f900750c48723cb9df860fbb37dd25',
        'type' => 'library',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        '__root__' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => 'c5b7bc6035f900750c48723cb9df860fbb37dd25',
            'type' => 'library',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'phpstan/phpstan' => array(
            'pretty_version' => '1.8.4',
            'version' => '1.8.4.0',
            'reference' => 'eed4c9da531f6ebb4787235b6fb486e2c20f34e5',
            'type' => 'library',
            'install_path' => __DIR__ . '/../phpstan/phpstan',
            'aliases' => array(),
            'dev_requirement' => true,
        ),
        'rector/rector' => array(
            'pretty_version' => '0.14.2',
            'version' => '0.14.2.0',
            'reference' => '55915c3dea8ea39ee8ad6964b2bf2b5226d47131',
            'type' => 'library',
            'install_path' => __DIR__ . '/../rector/rector',
            'aliases' => array(),
            'dev_requirement' => true,
        ),
    ),
);
