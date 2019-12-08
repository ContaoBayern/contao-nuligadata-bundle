<?php declare(strict_types=1);

namespace ContaoBayern\NuligadataBundle\ContaoManager;

use ContaoBayern\NuligadataBundle\ContaoBayernNuligadataBundle;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CalendarBundle\ContaoCalendarBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;

// use Symfony\Component\Config\Loader\LoaderResolverInterface;
// use Symfony\Component\HttpKernel\KernelInterface;

class Plugin implements BundlePluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(ContaoBayernNuligadataBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class, ContaoCalendarBundle::class]),
        ];
    }

}
