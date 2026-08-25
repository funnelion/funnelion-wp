<?php return array(
    'root' => array(
        'name' => 'funnelion/wordpress',
        'pretty_version' => '1.0.0+no-version-set',
        'version' => '1.0.0.0',
        'reference' => null,
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'funnelion/sdk' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '9f22570317559d737885c85c2ca83338a4e7ddfe',
            'type' => 'library',
            'install_path' => __DIR__ . '/../funnelion/sdk',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'funnelion/wordpress' => array(
            'pretty_version' => '1.0.0+no-version-set',
            'version' => '1.0.0.0',
            'reference' => null,
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
