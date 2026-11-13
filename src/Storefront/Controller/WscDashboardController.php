<?php

declare(strict_types=1);

namespace Wsc\Analytics\Storefront\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Storefront\Controller\StorefrontController;
use Wsc\Analytics\Service\WscCoreService;

/**
 * Controller for rendering the WSC v2.2.3 Bento Analytics & Search Health Dashboard in Shopware 6.
 *
 * @Route(defaults={"_routeScope"={"storefront"}})
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class WscDashboardController extends StorefrontController
{
    private WscCoreService $coreService;

    public function __construct(WscCoreService $coreService)
    {
        $this->coreService = $coreService;
    }

    /**
     * @Route("/wsc/admin/dashboard", name="frontend.wsc.dashboard", methods={"GET"})
     */
    #[Route(path: '/wsc/admin/dashboard', name: 'frontend.wsc.dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        $siteUrl = $request->getSchemeAndHttpHost();
        $cards = $this->coreService->getDashboardCards();
        $creds = $this->coreService->getProjectCredentials(false, null, $siteUrl);
        $membership = $this->coreService->getMembershipState();
        $partners = $this->coreService->getFeaturedPartners(3);
        $benchmarks = $this->coreService->getBenchmarkReport(7, false, null, $siteUrl);

        $hasData = !empty($cards['card1_traffic'])
            || !empty($cards['card2_insights'])
            || !empty($cards['card3_bots'])
            || !empty($cards['card_engagement'])
            || !empty($cards['card_outbound'])
            || !empty($cards['card_ecommerce'])
            || !empty($cards['domain_health']);

        return $this->renderStorefront('@WscAnalytics/storefront/page/wsc-dashboard.html.twig', [
            'version'         => WscCoreService::VERSION,
            'cards'           => $cards,
            'projectId'       => $creds['project_id'] ?: 'Local Mode (Opt-In Cloud Sync)',
            'hybridEnabled'   => $creds['hybrid'],
            'hasData'         => $hasData,
            'siteHost'        => $request->getHost(),
            'siteUrl'         => $siteUrl,
            'membership'      => $membership,
            'partners'        => $partners,
            'benchmarks'      => $benchmarks,
            'llmsTxtEnabled'  => $this->coreService->isLlmsTxtEnabled(),
            'indexnowEnabled' => $this->coreService->isIndexNowEnabled(),
            'indexnowKey'     => $this->coreService->getIndexNowKey(),
            'indexnowLogs'    => $this->coreService->getIndexNowLogs(),
        ]);
    }
}
