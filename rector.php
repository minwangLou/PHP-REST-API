<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Symfony\Set\JMSSetList;
use Rector\Symfony\Set\SensiolabsSetList;
use Rector\Symfony\Set\SymfonySetList;

return static function (RectorConfig $rectorConfig): void {
    // region Symfony Container
    $parameters = $rectorConfig->parameters();
    $rectorConfig->symfonyContainerXml(
        __DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml'
    );
    // endregion

    // Define what rule sets will be applied
    $rectorConfig->import(DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES);
    $rectorConfig->import(SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES);
    $rectorConfig->import(SensiolabsSetList::FRAMEWORK_EXTRA_61);
    $rectorConfig->import(JMSSetList::ANNOTATIONS_TO_ATTRIBUTES);
    // get services (needed for register a single rule)
    // $services = $containerConfigurator->services();

    // register a single rule
    // $services->set(TypedPropertyRector::class);    // Path to PHPStan with extensions, that PHPStan in Rector uses to determine types

    $rectorConfig->phpstanConfig(__DIR__ . '/phpstan.neon');
};
