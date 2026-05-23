<?php
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

define('BASEPATH', dirname(__FILE__));
require __DIR__ . '/library/Settings.php';
require __DIR__ . '/library/Analytics.php';

$method = $_POST['requestMethod'] ?? '';
$analytics = new Analytics();

switch ($method) {
    case 'getKpiCards':
        echo json_encode($analytics->getKpiCards());
        break;

    case 'getDailyClickTrend':
        $days = (int)($_POST['days'] ?? 30);
        echo json_encode($analytics->getDailyClickTrend($days));
        break;

    case 'getNetworkBreakdown':
        $days = (int)($_POST['days'] ?? 30);
        echo json_encode($analytics->getNetworkBreakdown($days));
        break;

    case 'getTopOffers':
        $days = (int)($_POST['days'] ?? 7);
        echo json_encode($analytics->getTopOffers($days));
        break;

    case 'getOfferHealthIssues':
        echo json_encode($analytics->getOfferHealthIssues());
        break;

    case 'getRoutingDistribution':
        $offer_id = (int)($_POST['offer_id'] ?? 0);
        if ($offer_id <= 0) {
            echo json_encode(['error' => 'Invalid offer_id']);
            break;
        }
        echo json_encode($analytics->getRoutingDistribution($offer_id));
        break;

    case 'getTrafficQuality':
        $days = (int)($_POST['days'] ?? 30);
        echo json_encode($analytics->getTrafficQuality($days));
        break;

    case 'getOfferListForDropdown':
        echo json_encode($analytics->getOfferListForDropdown());
        break;

    case 'getOfferAnalytics':
        echo json_encode($analytics->getOfferAnalytics());
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown requestMethod']);
        break;
}
