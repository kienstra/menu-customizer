<?php
/**
 * Custom Customizer Controls for the Menu Customizer.
 */

/**
 * Customize Nav Menu Control Class
 */
class WP_Customize_Nav_Menu_Control extends WP_Customize_Control {
	public $type = 'nav_menu';
	public $menu_id;

	/**
	 * Refresh the parameters passed to the JavaScript via JSON.
	 *
	 * @since Menu Customizer 0.0
	 */
	public function to_json() {
		parent::to_json();
		$this->json[ 'menu_id' ] = $this->menu_id;
		$this->json[ 'menu_name' ] = wp_get_nav_menu_object( $this->menu_id )->name;
	}

	/**
	 * Don't render the control's content - it uses a JS template instead.
	 *
	 * @since Menu Customizer 0.0
	 */
	public function render_content() {}

	/**
	 * JS/Underscore template for the control UI.
	 *
	 * @since Menu Customizer 0.2
	 */
	public function content_template() {
		?>
		<span class="button-secondary add-new-menu-item" tabindex="0">
			<?php _e( 'Add Links' ); ?>
		</span>
		<span class="add-menu-item-loading spinner"></span>
		<span class="reorder-toggle" tabindex="0">
			<span class="reorder"><?php _ex( 'Reorder', 'Reorder menu items in Customizer' ); ?></span>
			<span class="reorder-done"><?php _ex( 'Done', 'Cancel reordering menu items in Customizer'  ); ?></span>
		</span>
		<span class="menu-delete" id="delete-menu-{{ data.menu_id }}" tabindex="0">
			<span class="screen-reader-text"><?php _e( 'Delete menu:' ); ?> {{ data.menu_name }}</span>
		</span>
	<?php
	}
}

/**
 * Customize Menu Item Control Class
 */
class WP_Customize_Menu_Item_Control extends WP_Customize_Control {
	public $type = 'menu_item';
	public $menu_id = 0;
	public $item;
	public $menu_item_id = 0;
	public $original_id = 0;
	public $depth = 0;
	public $menu_item_parent_id = 0;

	/**
	 * Constructor.
	 *
	 * @uses WP_Customize_Control::__construct()
	 *
	 * @param WP_Customize_Manager $manager
	 * @param string $id
	 * @param array $args
	 */
	public function __construct( $manager, $id, $args = array() ) {
		parent::__construct( $manager, $id, $args );

		if ( $this->item ) {
			$this->menu_item_id = $this->item->ID;
			$this->original_id = $this->menu_item_id;
			$this->depth = $this->depth( $this->item->menu_item_parent, 0 );
			$this->menu_item_parent_id = $this->item->menu_item_parent;
		}
	}

	/**
	 * Refresh the parameters passed to the JavaScript via JSON.
	 *
	 * @since Menu Customizer 0.0
	 */
	public function to_json() {
		parent::to_json();

		$item = $this->item;
		$item_id = $item->ID;

		// Menu item UI stuff.
		$original_title = '';
		if ( 'taxonomy' == $item->type ) {
			$original_title = get_term_field( 'name', $item->object_id, $item->object, 'raw' );
			if ( is_wp_error( $original_title ) ) {
				$original_title = false;
			}
		} elseif ( 'post_type' == $item->type ) {
			$original_object = get_post( $item->object_id );
			$original_title = get_the_title( $original_object->ID );
		}
		$this->json['original_title'] = $original_title;

		$classes = array(
			'menu-item menu-item-depth-' . $this->depth,
			'menu-item-' . esc_attr( $item->object ),
			'menu-item-edit-inactive',
		);
		$title = $item->title;
		if ( ! empty( $item->_invalid ) ) {
			$classes[] = 'menu-item-invalid';
			/* translators: %s: title of menu item which is invalid */
			$title = sprintf( __( '%s (Invalid)' ), $item->title );
		} elseif ( isset( $item->post_status ) && 'draft' == $item->post_status ) {
			$classes[] = 'pending';
			/* translators: %s: title of menu item in draft status */
			$title = sprintf( __('%s (Pending)'), $item->title );
		}
		$title = ( ! isset( $item->label ) || '' == $item->label ) ? $title : $item->label;
		$this->json['title'] = $title;
		$this->json['el_classes'] = implode(' ', $classes );

		$this->json['item_type_label'] = $item->type_label;
		$this->json['item_type'] = $item->type;
		$this->json['url'] = $item->url;
		$this->json['target'] = $item->target;
		$this->json['attr_title'] = $item->attr_title;
		$this->json['classes'] = implode(' ', $item->classes );
		$this->json['xfn'] = $item->xfn;
		$this->json['description'] = $item->description;
		$this->json['parent'] = $item->parent;

		// Control class fields.
		$exported_properties = array( 'menu_item_id', 'original_id', 'menu_id', 'depth', 'menu_item_parent_id' );
		foreach ( $exported_properties as $key ) {
			$this->json[ $key ] = $this->$key;
		}
	}

	/**
	 * Determine the depth of a menu item by recursion.
	 *
	 * @param int $parent_id The id of the parent menu item
	 * @param int $depth Inverse current item depth
	 *
	 * @returns int Depth of the original menu item.
	 */
	public function depth( $parent_id, $depth = 0 ) {
		if ( 0 == $parent_id ) {
			// This is a top-level item, so the current depth is the maximum.
			return $depth;
		} else {
			// Increase depth.
			$depth = $depth + 1;

			// Find menu item parent's parent menu item id (the grandparent id).
			$parent = get_post( $parent_id ); // WP_Post object.
			$parent = wp_setup_nav_menu_item( $parent ); // Adds menu item properties.
			$parent_parent_id = $parent->menu_item_parent;

			return $this->depth( $parent_parent_id, $depth );
		}
	}

	/**
	 * Renders the control wrapper and calls $this->render_content() for the internals.
	 *
	 * @since Menu Customizer 0.0
	 */
	protected function render() {
		$id    = 'customize-control-' . str_replace( '[', '-', str_replace( ']', '', $this->id ) );
		$class = 'customize-control customize-control-' . $this->type . ' nav-menu-item-wrap';

		?><li id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $class ); ?>">
			<?php $this->render_content(); ?>
		</li><?php
	}

	/**
	 * Don't render the control's content - it's rendered with a JS template.
	 *
	 * @since Menu Customizer 0.0
	 */
	public function render_content() {}

	/**
	 * JS/Underscore template for the control UI.
	 *
	 * @since Menu Customizer 0.2
	 */
	public function content_template() {
		?>
		<div id="menu-item-{{ data.menu_item_id }}" class="{{ data.el_classes }}" data-item-depth="{{ data.depth }}">
			<dl class="menu-item-bar">
				<dt class="menu-item-handle">
					<span class="item-type">{{ data.item_type_label }}</span>
					<span class="item-title">
						<span class="spinner"></span>
						<span class="menu-item-title">{{ data.title }}</span>
						<span class="is-submenu"><?php _e( 'sub item' ); ?></span>
					</span>
					<span class="item-controls">
						<a class="item-edit" id="edit-{{ data.menu_item_id }}" title="<?php esc_attr_e( 'Edit Menu Item' ); ?>" href="#"><?php _e( 'Edit Menu Item' ); ?></a>
					</span>
				</dt>
			</dl>

			<div class="menu-item-settings" id="menu-item-settings-{{ data.menu_item_id }}">
				<# if ( 'custom' == data.item_type ) { #>
					<p class="field-url description description-thin">
						<label for="edit-menu-item-url-{{ data.menu_item_id }}">
							<?php _e( 'URL' ); ?><br />
							<input class="widefat code edit-menu-item-url" type="text" value="{{ data.url }}" id="edit-menu-item-url-{{ data.menu_item_id }}" name="menu-item-url" />
						</label>
					</p>
				<# } #>
				<p class="description description-thin">
					<label for="edit-menu-item-title-{{ data.menu_item_id }}">
						<?php _e( 'Navigation Label' ); ?><br />
						<input type="text" id="edit-menu-item-title-{{ data.menu_item_id }}" class="widefat edit-menu-item-title" name="menu-item-title" value="{{ data.title }}" />
					</label>
				</p>
				<p class="field-link-target description description-thin">
					<label for="edit-menu-item-target-{{ data.menu_item_id }}">
						<input type="checkbox" id="edit-menu-item-target-{{ data.menu_item_id }}" value="_blank" name="menu-item-target" <# if ( '_blank' == data.target ) { #> checked="checked" <# } #> />
						<?php _e( 'Open link in a new tab' ); ?>
					</label>
				</p>
				<p class="field-attr-title description description-thin">
					<label for="edit-menu-item-attr-title-{{ data.menu_item_id }}">
						<?php _e( 'Title Attribute' ); ?><br />
						<input type="text" id="edit-menu-item-attr-title-{{ data.menu_item_id }}" class="widefat edit-menu-item-attr-title" name="menu-item-attr-title" value="{{ data.attr_title }}" />
					</label>
				</p>
				<p class="field-css-classes description description-thin">
					<label for="edit-menu-item-classes-{{ data.menu_item_id }}">
						<?php _e( 'CSS Classes' ); ?><br />
						<input type="text" id="edit-menu-item-classes-{{ data.menu_item_id }}" class="widefat code edit-menu-item-classes" name="menu-item-classes" value="{{ data.classes }}" />
					</label>
				</p>
				<p class="field-xfn description description-thin">
					<label for="edit-menu-item-xfn-{{ data.menu_item_id }}">
						<?php _e( 'Link Relationship (XFN)' ); ?><br />
						<input type="text" id="edit-menu-item-xfn-{{ data.menu_item_id }}" class="widefat code edit-menu-item-xfn" name="menu-item-xfn" value="{{ data.xfn }}" />
					</label>
				</p>
				<p class="field-description description description-thin">
					<label for="edit-menu-item-description-{{ data.menu_item_id }}">
						<?php _e( 'Description' ); ?><br />
						<textarea id="edit-menu-item-description-{{ data.menu_item_id }}" class="widefat edit-menu-item-description" rows="3" cols="20" name="menu-item-description">{{ data.description }}</textarea>
						<span class="description"><?php _e( 'The description will be displayed in the menu if the current theme supports it.' ); ?></span>
					</label>
				</p>

				<div class="menu-item-actions description-thin submitbox">
					<# if ( 'custom' != data.item_type && data.original_title !== false ) { #>
						<p class="link-to-original">
							<?php _e( 'Original:' ); ?> <a href="{{ data.url }}" target="_blank">{{ data.original_title }}</a>
						</p>
					<# } #>
					<a class="item-delete submitdelete deletion" id="delete-menu-item-{{ data.menu_item_id }}" href="#"><?php _e( 'Remove' ); ?></a>
				</div>
				<input type="hidden" name="menu-item-parent-id" class="menu-item-parent-id" id="edit-menu-item-parent-id-{{ data.menu_item_id }}" value="{{ data.parent }}" />
			</div><!-- .menu-item-settings-->
			<ul class="menu-item-transport"></ul>
		</div>
		<?php
	}
}

/**
 * Outputs the screen options controls from nav-menus.php.
 */
class WP_Menu_Options_Customize_Control extends WP_Customize_Control {
	public $type = 'menu_options';

	public function render_content() {
		// Essentially adds the screen options.
		add_filter( 'manage_nav-menus_columns', array( $this, 'wp_nav_menu_manage_columns' ) );

		// Display screen options.
		$screen = WP_Screen::get( 'nav-menus.php' );
		$screen->render_screen_options();
	}

	/**
	 * Copied from wp-admin/includes/nav-menu.php. Returns the advanced options for the nav menus page.
	 *
	 * Link title attribute added as it's a relatively advanced concept for new users.
	 *
	 * @since 0.0
	 *
	 * @return Array The advanced menu properties.
	 */
	function wp_nav_menu_manage_columns() {
		return array(
			'_title' => __('Show advanced menu properties'),
			'cb' => '<input type="checkbox" />',
			'link-target' => __('Link Target'),
			'attr-title' => __('Title Attribute'),
			'css-classes' => __('CSS Classes'),
			'xfn' => __('Link Relationship (XFN)'),
			'description' => __('Description'),
		);
	}
}


/**
 * New Menu Customize Control Class
 */
class WP_New_Menu_Customize_Control extends WP_Customize_Control {
	public $type = 'new_menu';

	/**
	 * Render the control's content.
	 *
	 * @since Menu Customizer 0.0
	 */
	public function render_content() {
		?>
		<span class="button button-primary" id="create-new-menu-submit" tabindex="0"><?php _e( 'Create Menu' ); ?></span>
		<span class="spinner"></span>
		<span class="button" id="toggle-menu-delete" tabindex="0"><?php _e( 'Delete an Existing Menu' ); ?></span>
		<?php
	}
}
