<?php
defined('ABSPATH') || exit;

/**
 * Elementor widgeti koji wrapuju shortcode-e:
 * - OVB Apartments (lista)
 * - OVB Apartment Filter (forma)
 */

add_action('elementor/widgets/register', function($widgets_manager){
    if ( ! class_exists('\Elementor\Widget_Base') ) return;

    /* ==================== Apartments List ==================== */
    class OVB_Widget_Apartments extends \Elementor\Widget_Base {
        public function get_name(){ return 'ovb_apartments'; }
        public function get_title(){ return __('OVB Apartments', 'ov-booking'); }
        public function get_icon(){ return 'eicon-products'; }
        public function get_categories(){ return ['general']; }

        protected function register_controls(){
            $this->start_controls_section('content', ['label' => __('Content', 'ov-booking')]);

            $this->add_control('per_page', [
                'label' => __('Per page','ov-booking'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 12,
                'min' => 1, 'max' => 48,
            ]);
            $this->add_control('columns', [
                'label' => __('Columns','ov-booking'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 3,
                'min' => 1, 'max' => 6,
            ]);
            $this->add_control('show_min_price', [
                'label' => __('Show min price (/night)','ov-booking'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'no',
            ]);
            $this->add_control('window_days', [
                'label' => __('Scan days ahead','ov-booking'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 365,
            ]);
            $this->add_control('category', [
                'label' => __('Categories (slugs, CSV)','ov-booking'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'sea-view,city-center',
            ]);
            $this->add_control('city', [
                'label' => __('Prefilter City','ov-booking'),
                'type' => \Elementor\Controls_Manager::TEXT,
            ]);
            $this->add_control('country', [
                'label' => __('Prefilter Country','ov-booking'),
                'type' => \Elementor\Controls_Manager::TEXT,
            ]);
            $this->add_control('guests', [
                'label' => __('Min guests','ov-booking'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 0, 'default' => 0,
            ]);
            $this->add_control('rooms', [
                'label' => __('Min rooms','ov-booking'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 0, 'default' => 0,
            ]);
            $this->end_controls_section();
        }

        protected function render(){
            $s = $this->get_settings_for_display();
            echo do_shortcode(sprintf(
                '[ovb_apartments per_page="%d" columns="%d" show_min_price="%d" window_days="%d" category="%s" city="%s" country="%s" guests="%s" rooms="%s"]',
                intval($s['per_page']), intval($s['columns']),
                ($s['show_min_price']==='yes'?1:0),
                intval($s['window_days']),
                esc_attr($s['category']), esc_attr($s['city']), esc_attr($s['country']),
                esc_attr($s['guests']), esc_attr($s['rooms'])
            ));
        }
    }
    $widgets_manager->register(new OVB_Widget_Apartments());

    /* ==================== Apartments Filter ==================== */
    class OVB_Widget_Apartment_Filter extends \Elementor\Widget_Base {
        public function get_name(){ return 'ovb_apartment_filter'; }
        public function get_title(){ return __('OVB Apartment Filter', 'ov-booking'); }
        public function get_icon(){ return 'eicon-filter'; }
        public function get_categories(){ return ['general']; }

        protected function register_controls(){
            $this->start_controls_section('content', ['label' => __('Content', 'ov-booking')]);
            $this->start_controls_section(
                'style_section',
                ['label' => __('Stilizacija', 'ov-booking'), 'tab' => \Elementor\Controls_Manager::TAB_STYLE]
            );
            $this->add_control(
                'heading_color',
                [
                    'label' => __('Boja naslova', 'ov-booking'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'selectors' => ['{{WRAPPER}} .ovb-apartments-grid .woocommerce-loop-product__title' => 'color: {{VALUE}};'],
                ]
            );
            $this->add_group_control(
                \Elementor\Group_Control_Typography::get_type(),
                [
                    'name' => 'heading_typography',
                    'selector' => '{{WRAPPER}} .ovb-apartments-grid .woocommerce-loop-product__title',
                ]
            );
            $this->add_responsive_control(
                'item_padding',
                [
                    'label' => __('Padding itema', 'ov-booking'),
                    'type' => \Elementor\Controls_Manager::DIMENSIONS,
                    'size_units' => ['px','em','%'],
                    'selectors' => ['{{WRAPPER}} .ovb-apartments-grid .ovb-apartment-item' =>
                        'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
                ]
            );
            $this->end_controls_section();

            $this->add_control('show_country', [
                'label' => __('Show country field','ov-booking'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'no',
            ]);

            $this->add_control('redirect', [
                'label' => __('Redirect to (leave empty for Shop)','ov-booking'),
                'type' => \Elementor\Controls_Manager::URL,
                'placeholder' => '',
            ]);
            $this->add_control('btn_label', [
                'label' => __('Button label','ov-booking'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Search','ov-booking'),
            ]);
            $this->start_controls_section(
                'filters_section',
                ['label' => __('Podešavanja filtera', 'ov-booking'), 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]
            );

            $repeater = new \Elementor\Repeater();
            $repeater->add_control('filter_type', [
                'label' => __('Filter', 'ov-booking'),
                'type'  => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'date'    => __('Datumi (Check-in/Check-out jedan dan)', 'ov-booking'),
                    'guests'  => __('Broj gostiju', 'ov-booking'),
                    'rooms'   => __('Broj soba', 'ov-booking'),
                    'city'    => __('Grad', 'ov-booking'),
                    'country' => __('Država', 'ov-booking'),
                ],
                'default' => 'date',
            ]);

            $this->add_control('filters_list', [
                'label' => __('Lista filtera', 'ov-booking'),
                'type'  => \Elementor\Controls_Manager::REPEATER,
                'fields'=> $repeater->get_controls(),
                'default' => [
                    ['filter_type' => 'date'],
                    ['filter_type' => 'guests'],
                ],
                'title_field' => '{{{ filter_type }}}',
            ]);
            $this->end_controls_section();
        }

        protected function render(){
            $s = $this->get_settings_for_display();
            $redir = '';
            if (!empty($s['redirect']['url'])) $redir = esc_url_raw($s['redirect']['url']);

          $fields_csv = '';
if (!empty($s['filters_list']) && is_array($s['filters_list'])) {
    $fields_csv = implode(',', array_map(fn($it) => $it['filter_type'] ?? '', $s['filters_list']));
    $fields_csv = trim($fields_csv, ',');
}
echo do_shortcode(sprintf(
    '[ovb_apartment_filter fields="%s" show_country="%d" redirect="%s" btn_label="%s"]',
    esc_attr($fields_csv),
    ($s['show_country']==='yes'?1:0),
    esc_attr($redir),
    esc_attr($s['btn_label'])
));
        }
    }
    $widgets_manager->register(new OVB_Widget_Apartment_Filter());
});