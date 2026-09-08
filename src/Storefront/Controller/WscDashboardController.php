<?php

declare(strict_types=1);

namespace Wsc\Analytics\Storefront\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Storefront\Controller\StorefrontController;
use Wsc\Analytics\Service\WscCoreService;

/**
 * Controller for rendering the WSC 3-Card Analytics Dashboard in Shopware 6.
 *
 * @Route(defaults={"_routeScope"={"storefront"}})
 */
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
    public function dashboard(\Symfony\Component\HttpFoundation\Request $request): Response
    {
        $cards = $this->coreService->getDashboardCards();
        $creds = $this->coreService->getProjectCredentials();

        $hasData = !empty($cards['card1_traffic']) 
            || !empty($cards['card2_insights']) 
            || !empty($cards['card3_bots'])
            || !empty($cards['card_engagement'])
            || !empty($cards['card_outbound'])
            || !empty($cards['card_ecommerce'])
            || !empty($cards['domain_health']);

        return $this->renderStorefront('@WscAnalytics/storefront/page/wsc-dashboard.html.twig', [
            'cards'     => $cards,
            'projectId' => $creds['project_id'],
            'hasData'   => $hasData,
            'siteHost'  => $request->getHost(),
            'siteUrl'   => $request->getSchemeAndHttpHost(),
        ]);
    }
}
