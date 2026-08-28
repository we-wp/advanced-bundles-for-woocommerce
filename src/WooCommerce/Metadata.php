<?php
/**
 * Owned cart and order metadata names.
 *
 * @package AIMPlugins\AdvancedBundles
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AIMPlugins\AdvancedBundles\WooCommerce;

final class Metadata {
	public const GROUP_ID             = '_aim_bundle_group_id';
	public const ROLE                 = '_aim_bundle_role';
	public const ROLE_PARENT          = 'parent';
	public const ROLE_COMPONENT       = 'component';
	public const PARENT_CART_ITEM_KEY = '_aim_bundle_parent_cart_item_key';
	public const PARENT_PRODUCT_ID    = '_aim_bundle_parent_product_id';
	public const COMPONENT_ID         = '_aim_bundle_component_id';
	public const COMPONENT_QUANTITY   = '_aim_bundle_component_quantity';
	public const DEFINITION           = '_aim_bundle_definition';
	public const DEFINITION_ID        = '_aim_bundle_definition_id';
	public const DEFINITION_VERSION   = '_aim_bundle_definition_version';
	public const SNAPSHOT_MONEY_SCALE = '_aim_bundle_snapshot_money_scale';

	private function __construct() {}
}
