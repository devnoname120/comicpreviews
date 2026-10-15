<?php

declare(strict_types=1);

namespace OCA\Comicpreviews\AppInfo;

use OCA\Comicpreviews\Preview\CbzPreviewProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

final class Application extends App implements IBootstrap {
    public const APP_ID = 'comicpreviews';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerPreviewProvider(
            CbzPreviewProvider::class,
            '/^application\/(?:comicbook\+zip|vnd\.comicbook\+zip|zip|comicbook\+rar)$/'
        );
    }

    public function boot(IBootContext $context): void {
    }
}
