<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Export_API
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        $namespace = 'wc-export/v1';

        register_rest_route($namespace, '/orders', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'handle_orders'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => $this->common_args(['status' => [
                    'description' => 'Comma-separated order statuses (e.g., completed,processing).',
                    'type' => 'string',
                    'required' => false,
                ],]),
            ],
        ]);

        register_rest_route($namespace, '/products', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'handle_products'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => $this->common_args(['status' => [
                    'description' => 'Product status (any, publish, draft).',
                    'type' => 'string',
                    'required' => false,
                    'default' => 'any',
                ],]),
            ],
        ]);

        register_rest_route($namespace, '/customers', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'handle_customers'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => $this->common_args([]),
            ],
        ]);
    }

    public function check_permissions(): bool
    {
        // Require a logged-in user with manage_woocommerce capability.
        // For external usage, use Application Passwords or Basic Auth.
        return current_user_can('manage_woocommerce');
    }

    private function common_args(array $extra): array
    {
        $base = [
            'format' => [
                'description' => 'Export format: json or csv',
                'type' => 'string',
                'enum' => ['json', 'csv'],
                'default' => 'json',
            ],
            'fields' => [
                'description' => 'Comma-separated list of fields to include',
                'type' => 'string',
                'required' => false,
            ],
            'per_page' => [
                'description' => 'Items per page (max 200)',
                'type' => 'integer',
                'default' => 100,
                'minimum' => 1,
                'maximum' => 200,
            ],
            'page' => [
                'description' => 'Page number (1-indexed)',
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
            ],
            'download' => [
                'description' => 'If 1, force file download for CSV',
                'type' => 'boolean',
                'default' => false,
            ],
            'delimiter' => [
                'description' => 'CSV delimiter character',
                'type' => 'string',
                'default' => ',',
            ],
            'date_min' => [
                'description' => 'Start date (YYYY-MM-DD)',
                'type' => 'string',
                'required' => false,
            ],
            'date_max' => [
                'description' => 'End date (YYYY-MM-DD)',
                'type' => 'string',
                'required' => false,
            ],
        ];
        return array_merge($base, $extra);
    }

    public function handle_orders(WP_REST_Request $request)
    {
        $perPage = max(1, min((int) $request->get_param('per_page'), 200));
        $page = max(1, (int) $request->get_param('page'));
        $format = $request->get_param('format') ?: 'json';
        $delimiter = $request->get_param('delimiter') ?: ',';
        $fieldsParam = trim((string) $request->get_param('fields'));
        $fields = $fieldsParam !== '' ? array_map('trim', explode(',', $fieldsParam)) : [];
        $download = (bool) $request->get_param('download');

        $statusParam = trim((string) $request->get_param('status'));
        $statuses = $statusParam !== '' ? array_map('trim', explode(',', $statusParam)) : [];
        $dateMin = trim((string) $request->get_param('date_min'));
        $dateMax = trim((string) $request->get_param('date_max'));

        $dateQuery = $this->build_date_query($dateMin, $dateMax);

        $args = [
            'paginate' => true,
            'limit' => $perPage,
            'page' => $page,
        ];
        if (!empty($statuses)) {
            $args['status'] = $statuses; // accepts e.g. ['completed','processing']
        }
        if (!empty($dateQuery)) {
            $args['date_created'] = $dateQuery;
        }

        $result = wc_get_orders($args);
        $orders = isset($result['orders']) ? $result['orders'] : [];
        $total = isset($result['total']) ? (int) $result['total'] : count($orders);
        $maxPages = isset($result['max_num_pages']) ? (int) $result['max_num_pages'] : 1;

        $rows = [];
        foreach ($orders as $order) {
            if (!$order instanceof WC_Order) {
                $order = wc_get_order($order);
            }
            if ($order) {
                $rows[] = $this->format_order($order);
            }
        }

        if (!empty($fields)) {
            $rows = WC_Export_Formatter::filter_fields($rows, $fields);
        }

        if ($format === 'csv') {
            $csv = WC_Export_Formatter::to_csv($rows, !empty($fields) ? $fields : null, $delimiter);
            $response = new WP_REST_Response($csv, 200);
            $response->header('Content-Type', 'text/csv; charset=utf-8');
            $response->header('X-WP-Total', (string) $total);
            $response->header('X-WP-TotalPages', (string) $maxPages);
            if ($download) {
                $response->header('Content-Disposition', 'attachment; filename=' . WC_Export_Formatter::safe_filename('orders'));
            }
            return $response;
        }

        $response = new WP_REST_Response([
            'data' => $rows,
            'total' => $total,
            'pages' => $maxPages,
            'page' => $page,
            'per_page' => $perPage,
        ], 200);
        $response->header('X-WP-Total', (string) $total);
        $response->header('X-WP-TotalPages', (string) $maxPages);
        return $response;
    }

    public function handle_products(WP_REST_Request $request)
    {
        $perPage = max(1, min((int) $request->get_param('per_page'), 200));
        $page = max(1, (int) $request->get_param('page'));
        $format = $request->get_param('format') ?: 'json';
        $delimiter = $request->get_param('delimiter') ?: ',';
        $fieldsParam = trim((string) $request->get_param('fields'));
        $fields = $fieldsParam !== '' ? array_map('trim', explode(',', $fieldsParam)) : [];
        $download = (bool) $request->get_param('download');

        $status = $request->get_param('status') ?: 'any';
        $dateMin = trim((string) $request->get_param('date_min'));
        $dateMax = trim((string) $request->get_param('date_max'));
        $dateQuery = $this->build_date_query($dateMin, $dateMax);

        $args = [
            'paginate' => true,
            'limit' => $perPage,
            'page' => $page,
            'status' => $status,
        ];
        if (!empty($dateQuery)) {
            $args['date_created'] = $dateQuery;
        }

        $result = wc_get_products($args);
        $products = isset($result['products']) ? $result['products'] : (is_array($result) ? $result : []);
        $total = isset($result['total']) ? (int) $result['total'] : count($products);
        $maxPages = isset($result['max_num_pages']) ? (int) $result['max_num_pages'] : 1;

        $rows = [];
        foreach ($products as $product) {
            if (!$product instanceof WC_Product) {
                $product = wc_get_product($product);
            }
            if ($product) {
                $rows[] = $this->format_product($product);
            }
        }

        if (!empty($fields)) {
            $rows = WC_Export_Formatter::filter_fields($rows, $fields);
        }

        if ($format === 'csv') {
            $csv = WC_Export_Formatter::to_csv($rows, !empty($fields) ? $fields : null, $delimiter);
            $response = new WP_REST_Response($csv, 200);
            $response->header('Content-Type', 'text/csv; charset=utf-8');
            $response->header('X-WP-Total', (string) $total);
            $response->header('X-WP-TotalPages', (string) $maxPages);
            if ($download) {
                $response->header('Content-Disposition', 'attachment; filename=' . WC_Export_Formatter::safe_filename('products'));
            }
            return $response;
        }

        $response = new WP_REST_Response([
            'data' => $rows,
            'total' => $total,
            'pages' => $maxPages,
            'page' => $page,
            'per_page' => $perPage,
        ], 200);
        $response->header('X-WP-Total', (string) $total);
        $response->header('X-WP-TotalPages', (string) $maxPages);
        return $response;
    }

    public function handle_customers(WP_REST_Request $request)
    {
        $perPage = max(1, min((int) $request->get_param('per_page'), 200));
        $page = max(1, (int) $request->get_param('page'));
        $format = $request->get_param('format') ?: 'json';
        $delimiter = $request->get_param('delimiter') ?: ',';
        $fieldsParam = trim((string) $request->get_param('fields'));
        $fields = $fieldsParam !== '' ? array_map('trim', explode(',', $fieldsParam)) : [];
        $download = (bool) $request->get_param('download');

        $dateMin = trim((string) $request->get_param('date_min'));
        $dateMax = trim((string) $request->get_param('date_max'));
        $dateQuery = $this->build_date_query($dateMin, $dateMax);

        $queryArgs = [
            'role__in' => ['customer', 'subscriber'],
            'number' => $perPage,
            'paged' => $page,
            'fields' => ['ID', 'user_login', 'user_email', 'display_name', 'user_registered'],
        ];
        if (!empty($dateQuery)) {
            $dq = [];
            if (!empty($dateQuery['after'])) {
                $dq['after'] = $dateQuery['after'];
            }
            if (!empty($dateQuery['before'])) {
                $dq['before'] = $dateQuery['before'];
            }
            if (!empty($dq)) {
                $queryArgs['date_query'] = [$dq];
            }
        }

        $userQuery = new WP_User_Query($queryArgs);
        $users = (array) $userQuery->get_results();
        $total = (int) $userQuery->get_total();
        $maxPages = (int) ceil($total / $perPage);

        $rows = [];
        foreach ($users as $user) {
            if ($user instanceof WP_User) {
                $rows[] = $this->format_customer($user);
            }
        }

        if (!empty($fields)) {
            $rows = WC_Export_Formatter::filter_fields($rows, $fields);
        }

        if ($format === 'csv') {
            $csv = WC_Export_Formatter::to_csv($rows, !empty($fields) ? $fields : null, $delimiter);
            $response = new WP_REST_Response($csv, 200);
            $response->header('Content-Type', 'text/csv; charset=utf-8');
            $response->header('X-WP-Total', (string) $total);
            $response->header('X-WP-TotalPages', (string) $maxPages);
            if ($download) {
                $response->header('Content-Disposition', 'attachment; filename=' . WC_Export_Formatter::safe_filename('customers'));
            }
            return $response;
        }

        $response = new WP_REST_Response([
            'data' => $rows,
            'total' => $total,
            'pages' => $maxPages,
            'page' => $page,
            'per_page' => $perPage,
        ], 200);
        $response->header('X-WP-Total', (string) $total);
        $response->header('X-WP-TotalPages', (string) $maxPages);
        return $response;
    }

    private function build_date_query(?string $dateMin, ?string $dateMax): array
    {
        $after = null;
        $before = null;
        if (!empty($dateMin)) {
            $timestamp = strtotime($dateMin . ' 00:00:00');
            if ($timestamp) {
                $after = gmdate('Y-m-d H:i:s', $timestamp);
            }
        }
        if (!empty($dateMax)) {
            $timestamp = strtotime($dateMax . ' 23:59:59');
            if ($timestamp) {
                $before = gmdate('Y-m-d H:i:s', $timestamp);
            }
        }
        $out = [];
        if ($after) { $out['after'] = $after; }
        if ($before) { $out['before'] = $before; }
        if (!empty($out)) {
            $out['inclusive'] = true;
        }
        return $out;
    }

    private function format_order(WC_Order $order): array
    {
        $items = [];
        foreach ($order->get_items() as $item) {
            if ($item instanceof WC_Order_Item_Product) {
                $product = $item->get_product();
                $sku = $product ? $product->get_sku() : '';
                $items[] = [
                    'name' => $item->get_name(),
                    'product_id' => $item->get_product_id(),
                    'variation_id' => $item->get_variation_id(),
                    'sku' => $sku,
                    'quantity' => $item->get_quantity(),
                    'subtotal' => wc_format_decimal($item->get_subtotal(), 2),
                    'total' => wc_format_decimal($item->get_total(), 2),
                ];
            }
        }

        return [
            'id' => $order->get_id(),
            'number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'currency' => $order->get_currency(),
            'date_created' => $order->get_date_created() ? $order->get_date_created()->date('c') : '',
            'date_modified' => $order->get_date_modified() ? $order->get_date_modified()->date('c') : '',
            'customer_id' => $order->get_user_id(),
            'customer_email' => $order->get_billing_email(),
            'payment_method' => $order->get_payment_method(),
            'shipping_total' => wc_format_decimal($order->get_shipping_total(), 2),
            'discount_total' => wc_format_decimal($order->get_discount_total(), 2),
            'total' => wc_format_decimal($order->get_total(), 2),

            'billing_first_name' => $order->get_billing_first_name(),
            'billing_last_name' => $order->get_billing_last_name(),
            'billing_company' => $order->get_billing_company(),
            'billing_address_1' => $order->get_billing_address_1(),
            'billing_address_2' => $order->get_billing_address_2(),
            'billing_city' => $order->get_billing_city(),
            'billing_state' => $order->get_billing_state(),
            'billing_postcode' => $order->get_billing_postcode(),
            'billing_country' => $order->get_billing_country(),
            'billing_phone' => $order->get_billing_phone(),

            'shipping_first_name' => $order->get_shipping_first_name(),
            'shipping_last_name' => $order->get_shipping_last_name(),
            'shipping_company' => $order->get_shipping_company(),
            'shipping_address_1' => $order->get_shipping_address_1(),
            'shipping_address_2' => $order->get_shipping_address_2(),
            'shipping_city' => $order->get_shipping_city(),
            'shipping_state' => $order->get_shipping_state(),
            'shipping_postcode' => $order->get_shipping_postcode(),
            'shipping_country' => $order->get_shipping_country(),

            'items' => $items,
        ];
    }

    private function format_product(WC_Product $product): array
    {
        $categories = [];
        $terms = get_the_terms($product->get_id(), 'product_cat');
        if (is_array($terms)) {
            foreach ($terms as $term) {
                $categories[] = $term->name;
            }
        }
        $tags = [];
        $tagTerms = get_the_terms($product->get_id(), 'product_tag');
        if (is_array($tagTerms)) {
            foreach ($tagTerms as $term) {
                $tags[] = $term->name;
            }
        }

        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'slug' => $product->get_slug(),
            'type' => $product->get_type(),
            'status' => $product->get_status(),
            'sku' => $product->get_sku(),
            'price' => wc_format_decimal($product->get_price(), 2),
            'regular_price' => wc_format_decimal($product->get_regular_price(), 2),
            'sale_price' => wc_format_decimal($product->get_sale_price(), 2),
            'stock_status' => $product->get_stock_status(),
            'stock_quantity' => is_null($product->get_stock_quantity()) ? '' : (int) $product->get_stock_quantity(),
            'categories' => $categories,
            'tags' => $tags,
            'date_created' => $product->get_date_created() ? $product->get_date_created()->date('c') : '',
            'date_modified' => $product->get_date_modified() ? $product->get_date_modified()->date('c') : '',
        ];
    }

    private function format_customer(WP_User $user): array
    {
        $userId = $user->ID;
        $billing = [
            'first_name' => get_user_meta($userId, 'billing_first_name', true),
            'last_name' => get_user_meta($userId, 'billing_last_name', true),
            'company' => get_user_meta($userId, 'billing_company', true),
            'address_1' => get_user_meta($userId, 'billing_address_1', true),
            'address_2' => get_user_meta($userId, 'billing_address_2', true),
            'city' => get_user_meta($userId, 'billing_city', true),
            'state' => get_user_meta($userId, 'billing_state', true),
            'postcode' => get_user_meta($userId, 'billing_postcode', true),
            'country' => get_user_meta($userId, 'billing_country', true),
            'phone' => get_user_meta($userId, 'billing_phone', true),
        ];
        $shipping = [
            'first_name' => get_user_meta($userId, 'shipping_first_name', true),
            'last_name' => get_user_meta($userId, 'shipping_last_name', true),
            'company' => get_user_meta($userId, 'shipping_company', true),
            'address_1' => get_user_meta($userId, 'shipping_address_1', true),
            'address_2' => get_user_meta($userId, 'shipping_address_2', true),
            'city' => get_user_meta($userId, 'shipping_city', true),
            'state' => get_user_meta($userId, 'shipping_state', true),
            'postcode' => get_user_meta($userId, 'shipping_postcode', true),
            'country' => get_user_meta($userId, 'shipping_country', true),
            'phone' => get_user_meta($userId, 'shipping_phone', true),
        ];

        if (function_exists('wc_get_customer_order_count')) {
            $orderCount = (int) wc_get_customer_order_count($userId);
        } else {
            $orderCount = 0;
        }
        if (function_exists('wc_get_customer_total_spent')) {
            $totalSpent = wc_format_decimal(wc_get_customer_total_spent($userId), 2);
        } else {
            $totalSpent = '0.00';
        }

        return [
            'id' => $userId,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'first_name' => get_user_meta($userId, 'first_name', true),
            'last_name' => get_user_meta($userId, 'last_name', true),
            'date_registered' => $user->user_registered,

            'billing_first_name' => $billing['first_name'],
            'billing_last_name' => $billing['last_name'],
            'billing_company' => $billing['company'],
            'billing_address_1' => $billing['address_1'],
            'billing_address_2' => $billing['address_2'],
            'billing_city' => $billing['city'],
            'billing_state' => $billing['state'],
            'billing_postcode' => $billing['postcode'],
            'billing_country' => $billing['country'],
            'billing_phone' => $billing['phone'],

            'shipping_first_name' => $shipping['first_name'],
            'shipping_last_name' => $shipping['last_name'],
            'shipping_company' => $shipping['company'],
            'shipping_address_1' => $shipping['address_1'],
            'shipping_address_2' => $shipping['address_2'],
            'shipping_city' => $shipping['city'],
            'shipping_state' => $shipping['state'],
            'shipping_postcode' => $shipping['postcode'],
            'shipping_country' => $shipping['country'],
            'shipping_phone' => $shipping['phone'],

            'order_count' => $orderCount,
            'total_spent' => $totalSpent,
        ];
    }
}
