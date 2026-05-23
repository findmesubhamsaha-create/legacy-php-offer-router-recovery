<?php
if (!defined('BASEPATH')) {
    exit('Direct script access is not allowed!');
}

class Analytics
{
    private function connect(): mysqli
    {
        $db = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
        if ($db->connect_error) {
            die(json_encode(['error' => 'DB connection failed']));
        }
        return $db;
    }

    public function getKpiCards(): array
    {
        $db = $this->connect();

        $clicks_today  = (int)$db->query("SELECT COUNT(*) AS c FROM tbl_click WHERE created_at = CURDATE()")->fetch_assoc()['c'];
        $active_offers = (int)$db->query("SELECT COUNT(*) AS c FROM tbl_offer_url WHERE offer_status = 1")->fetch_assoc()['c'];
        $active_routes = (int)$db->query("SELECT COUNT(*) AS c FROM tbl_sub_offer_url WHERE status = 'yes' AND deleted_status = 'no'")->fetch_assoc()['c'];
        $unique_ips    = (int)$db->query("SELECT COUNT(DISTINCT ip_address) AS c FROM tbl_click WHERE created_at = CURDATE()")->fetch_assoc()['c'];

        $db->close();
        return [
            'clicks_today'    => $clicks_today,
            'active_offers'   => $active_offers,
            'active_routes'   => $active_routes,
            'unique_ips_today' => $unique_ips,
        ];
    }

    public function getDailyClickTrend(int $days = 30): array
    {
        $days = max(1, min(90, $days));
        $db   = $this->connect();

        $res    = $db->query("SELECT created_at AS day, COUNT(*) AS clicks FROM tbl_click WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY) GROUP BY created_at ORDER BY created_at ASC");
        $result = [];
        while ($row = $res->fetch_assoc()) {
            $result[] = $row;
        }
        $db->close();
        return $result;
    }

    public function getNetworkBreakdown(int $days = 30): array
    {
        $days = max(1, min(90, $days));
        $db   = $this->connect();

        $res = $db->query(
            "SELECT n.network_name, COUNT(c.id) AS clicks
             FROM tbl_network n
             JOIN tbl_offer_url o ON n.id = o.network_id
             JOIN tbl_click c ON o.id = c.offer_id
             WHERE c.created_at >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
             GROUP BY n.id
             ORDER BY clicks DESC"
        );
        $result = [];
        while ($row = $res->fetch_assoc()) {
            $result[] = $row;
        }
        $db->close();
        return $result;
    }

    public function getTopOffers(int $days = 7): array
    {
        $days = max(1, min(90, $days));
        $db   = $this->connect();

        $res = $db->query(
            "SELECT o.id, o.offer, o.slug_name, n.network_name, COUNT(c.id) AS clicks
             FROM tbl_offer_url o
             LEFT JOIN tbl_network n ON o.network_id = n.id
             LEFT JOIN tbl_click c ON o.id = c.offer_id AND c.created_at >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
             WHERE o.offer_status = 1
             GROUP BY o.id
             ORDER BY clicks DESC
             LIMIT 10"
        );
        $result = [];
        while ($row = $res->fetch_assoc()) {
            $result[] = $row;
        }
        $db->close();
        return $result;
    }

    public function getOfferHealthIssues(): array
    {
        $db     = $this->connect();
        $issues = [];

        // Offers with no active sub-URLs
        $res = $db->query(
            "SELECT o.id, o.offer FROM tbl_offer_url o
             WHERE o.offer_status = 1
               AND (SELECT COUNT(*) FROM tbl_sub_offer_url s
                    WHERE s.main_offer_id = o.id AND s.status = 'yes' AND s.deleted_status = 'no') = 0"
        );
        while ($row = $res->fetch_assoc()) {
            $issues[] = ['offer_id' => $row['id'], 'offer' => $row['offer'], 'issue' => 'No active sub-URLs'];
        }

        // Active offers with 0 clicks in last 30 days
        $res = $db->query(
            "SELECT o.id, o.offer FROM tbl_offer_url o
             WHERE o.offer_status = 1
               AND (SELECT COUNT(*) FROM tbl_click c
                    WHERE c.offer_id = o.id AND c.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) = 0"
        );
        while ($row = $res->fetch_assoc()) {
            $issues[] = ['offer_id' => $row['id'], 'offer' => $row['offer'], 'issue' => 'No clicks in last 30 days'];
        }

        // Expired offers still active
        $res = $db->query(
            "SELECT id, offer, end_date FROM tbl_offer_url
             WHERE offer_status = 1
               AND end_date IS NOT NULL
               AND end_date != ''
               AND end_date != '0000-00-00'
               AND end_date < CURDATE()"
        );
        while ($row = $res->fetch_assoc()) {
            $issues[] = ['offer_id' => $row['id'], 'offer' => $row['offer'], 'issue' => 'Expired (' . $row['end_date'] . ') but still routing'];
        }

        $db->close();
        return $issues;
    }

    public function getRoutingDistribution(int $offer_id): array
    {
        $offer_id = (int)$offer_id;
        $db       = $this->connect();

        $res = $db->query(
            "SELECT s.id, s.sub_url, s.weight, COUNT(c.id) AS actual_clicks
             FROM tbl_sub_offer_url s
             LEFT JOIN tbl_click c ON c.sub_offer_id = s.id
             WHERE s.main_offer_id = {$offer_id} AND s.deleted_status = 'no'
             GROUP BY s.id
             ORDER BY s.id ASC"
        );
        $result = [];
        while ($row = $res->fetch_assoc()) {
            $result[] = $row;
        }
        $db->close();

        $total_clicks = array_sum(array_column($result, 'actual_clicks'));
        $total_weight = array_sum(array_column($result, 'weight'));

        foreach ($result as &$r) {
            $r['actual_pct'] = $total_clicks > 0 ? round((int)$r['actual_clicks'] / $total_clicks * 100, 1) : 0;
            $r['config_pct'] = $total_weight > 0 ? round((int)$r['weight'] / $total_weight * 100, 1) : 0;
        }
        return $result;
    }

    public function getTrafficQuality(int $days = 30): array
    {
        $days = max(1, min(90, $days));
        $db   = $this->connect();

        $res    = $db->query(
            "SELECT ip_address, COUNT(*) AS hits
             FROM tbl_click
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
               AND ip_address != ''
             GROUP BY ip_address
             HAVING hits > 1
             ORDER BY hits DESC
             LIMIT 20"
        );
        $dup_ips = [];
        while ($row = $res->fetch_assoc()) {
            $dup_ips[] = $row;
        }

        $res = $db->query(
            "SELECT click_id, COUNT(*) AS hits
             FROM tbl_click
             WHERE click_id != '' AND click_id != '0'
               AND created_at >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
             GROUP BY click_id
             HAVING hits > 1
             ORDER BY hits DESC
             LIMIT 20"
        );
        $dup_clicks = [];
        while ($row = $res->fetch_assoc()) {
            $dup_clicks[] = $row;
        }

        $db->close();
        return ['duplicate_ips' => $dup_ips, 'repeated_click_ids' => $dup_clicks];
    }

    public function getOfferListForDropdown(): array
    {
        $db  = $this->connect();
        $res = $db->query("SELECT id, offer FROM tbl_offer_url WHERE offer_status = 1 ORDER BY offer ASC");
        $result = [];
        while ($row = $res->fetch_assoc()) {
            $result[] = $row;
        }
        $db->close();
        return $result;
    }

    public function getOfferAnalytics(): array
    {
        $db  = $this->connect();
        $res = $db->query(
            "SELECT o.id, o.offer, o.slug_name, t.tag_name, n.network_name,
                    COUNT(DISTINCT CASE WHEN s.deleted_status = 'no' THEN s.id END) AS sub_offer_count,
                    SUM(CASE WHEN s.status = 'yes' AND s.deleted_status = 'no' THEN 1 ELSE 0 END) AS active_routes,
                    COUNT(c.id) AS total_clicks
             FROM tbl_offer_url o
             LEFT JOIN tbl_tag t ON o.tag_id = t.id
             LEFT JOIN tbl_network n ON o.network_id = n.id
             LEFT JOIN tbl_sub_offer_url s ON o.id = s.main_offer_id
             LEFT JOIN tbl_click c ON o.id = c.offer_id
             WHERE o.offer_status = 1
             GROUP BY o.id
             ORDER BY total_clicks DESC"
        );
        $result = [];
        while ($row = $res->fetch_assoc()) {
            $result[] = $row;
        }
        $db->close();
        return $result;
    }
}
