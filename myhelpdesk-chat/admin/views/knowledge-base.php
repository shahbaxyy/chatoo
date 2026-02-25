<?php
/**
 * Admin Knowledge Base view.
 *
 * Two-section layout: a categories list and an articles list.
 * Includes inline forms for creating and editing categories and articles.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$kb = new MHD_Knowledge_Base();

$categories = $kb->get_categories();
$articles   = $kb->get_articles();
?>
<div class="wrap mhd-knowledge-base-page">

	<h1><?php esc_html_e( 'Knowledge Base', 'myhelpdesk-chat' ); ?></h1>

	<!-- ============================================================
		 Section 1 – Categories
	============================================================= -->
	<div class="mhd-kb-section" style="margin-top:20px;">

		<h2 class="wp-heading-inline"><?php esc_html_e( 'Categories', 'myhelpdesk-chat' ); ?></h2>
		<button type="button" id="mhd-add-kb-cat-btn" class="page-title-action">
			<?php esc_html_e( 'Add Category', 'myhelpdesk-chat' ); ?>
		</button>

		<table class="wp-list-table widefat fixed striped" id="mhd-kb-categories-table" style="margin-top:10px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'myhelpdesk-chat' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'myhelpdesk-chat' ); ?></th>
					<th style="width:70px;"><?php esc_html_e( 'Icon', 'myhelpdesk-chat' ); ?></th>
					<th style="width:90px;"><?php esc_html_e( 'Articles', 'myhelpdesk-chat' ); ?></th>
					<th style="width:130px;"><?php esc_html_e( 'Actions', 'myhelpdesk-chat' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $categories ) ) : ?>
					<tr>
						<td colspan="5" style="text-align:center;padding:20px;">
							<?php esc_html_e( 'No categories found.', 'myhelpdesk-chat' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $categories as $cat ) : ?>
						<tr data-cat-id="<?php echo esc_attr( $cat->id ); ?>">
							<td><strong><?php echo esc_html( $cat->name ); ?></strong></td>
							<td><?php echo esc_html( $cat->slug ); ?></td>
							<td>
								<?php if ( ! empty( $cat->icon ) ) : ?>
									<span class="dashicons <?php echo esc_attr( $cat->icon ); ?>"></span>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( isset( $cat->article_count ) ? $cat->article_count : 0 ); ?></td>
							<td>
								<button type="button"
									class="button button-small mhd-edit-kb-cat"
									data-cat-id="<?php echo esc_attr( $cat->id ); ?>"
									data-name="<?php echo esc_attr( $cat->name ); ?>"
									data-slug="<?php echo esc_attr( $cat->slug ); ?>"
									data-icon="<?php echo esc_attr( $cat->icon ); ?>">
									<?php esc_html_e( 'Edit', 'myhelpdesk-chat' ); ?>
								</button>
								<button type="button"
									class="button button-small button-link-delete mhd-delete-kb-cat"
									data-cat-id="<?php echo esc_attr( $cat->id ); ?>">
									<?php esc_html_e( 'Delete', 'myhelpdesk-chat' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<!-- Category Add / Edit Form -->
		<div id="mhd-kb-cat-form-wrap" style="display:none;margin-top:15px;background:#fff;border:1px solid #ccd0d4;padding:20px;border-radius:4px;max-width:500px;">
			<h3 id="mhd-kb-cat-form-title"><?php esc_html_e( 'Add Category', 'myhelpdesk-chat' ); ?></h3>
			<form id="mhd-kb-cat-form">
				<input type="hidden" id="mhd-kb-cat-id" name="cat_id" value="" />
				<p>
					<label for="mhd-kb-cat-name"><?php esc_html_e( 'Name', 'myhelpdesk-chat' ); ?></label><br />
					<input type="text" id="mhd-kb-cat-name" name="name" class="regular-text" required />
				</p>
				<p>
					<label for="mhd-kb-cat-slug"><?php esc_html_e( 'Slug', 'myhelpdesk-chat' ); ?></label><br />
					<input type="text" id="mhd-kb-cat-slug" name="slug" class="regular-text" />
				</p>
				<p>
					<label for="mhd-kb-cat-icon"><?php esc_html_e( 'Icon (dashicons class)', 'myhelpdesk-chat' ); ?></label><br />
					<input type="text" id="mhd-kb-cat-icon" name="icon" class="regular-text"
						placeholder="<?php esc_attr_e( 'e.g. dashicons-book', 'myhelpdesk-chat' ); ?>" />
				</p>
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Category', 'myhelpdesk-chat' ); ?>
				</button>
				<button type="button" id="mhd-kb-cat-form-cancel" class="button">
					<?php esc_html_e( 'Cancel', 'myhelpdesk-chat' ); ?>
				</button>
			</form>
		</div>
	</div><!-- .mhd-kb-section (categories) -->

	<hr style="margin:30px 0;" />

	<!-- ============================================================
		 Section 2 – Articles
	============================================================= -->
	<div class="mhd-kb-section">

		<h2 class="wp-heading-inline"><?php esc_html_e( 'Articles', 'myhelpdesk-chat' ); ?></h2>
		<button type="button" id="mhd-add-kb-article-btn" class="page-title-action">
			<?php esc_html_e( 'Add Article', 'myhelpdesk-chat' ); ?>
		</button>

		<table class="wp-list-table widefat fixed striped" id="mhd-kb-articles-table" style="margin-top:10px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Title', 'myhelpdesk-chat' ); ?></th>
					<th><?php esc_html_e( 'Category', 'myhelpdesk-chat' ); ?></th>
					<th style="width:90px;"><?php esc_html_e( 'Status', 'myhelpdesk-chat' ); ?></th>
					<th style="width:70px;"><?php esc_html_e( 'Views', 'myhelpdesk-chat' ); ?></th>
					<th style="width:140px;"><?php esc_html_e( 'Date', 'myhelpdesk-chat' ); ?></th>
					<th style="width:130px;"><?php esc_html_e( 'Actions', 'myhelpdesk-chat' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $articles ) ) : ?>
					<tr>
						<td colspan="6" style="text-align:center;padding:20px;">
							<?php esc_html_e( 'No articles found.', 'myhelpdesk-chat' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $articles as $article ) : ?>
						<?php
						$cat_name = '—';
						foreach ( $categories as $cat ) {
							if ( (int) $cat->id === (int) $article->category_id ) {
								$cat_name = $cat->name;
								break;
							}
						}
						?>
						<tr data-article-id="<?php echo esc_attr( $article->id ); ?>">
							<td><strong><?php echo esc_html( $article->title ); ?></strong></td>
							<td><?php echo esc_html( $cat_name ); ?></td>
							<td><?php echo esc_html( ucfirst( $article->status ) ); ?></td>
							<td><?php echo esc_html( isset( $article->views ) ? number_format_i18n( $article->views ) : 0 ); ?></td>
							<td><?php echo esc_html( $article->created_at ); ?></td>
							<td>
								<button type="button"
									class="button button-small mhd-edit-kb-article"
									data-article-id="<?php echo esc_attr( $article->id ); ?>">
									<?php esc_html_e( 'Edit', 'myhelpdesk-chat' ); ?>
								</button>
								<button type="button"
									class="button button-small button-link-delete mhd-delete-kb-article"
									data-article-id="<?php echo esc_attr( $article->id ); ?>">
									<?php esc_html_e( 'Delete', 'myhelpdesk-chat' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<!-- Article Add / Edit Form -->
		<div id="mhd-kb-article-form-wrap" style="display:none;margin-top:15px;background:#fff;border:1px solid #ccd0d4;padding:20px;border-radius:4px;max-width:700px;">
			<h3 id="mhd-kb-article-form-title"><?php esc_html_e( 'Add Article', 'myhelpdesk-chat' ); ?></h3>
			<form id="mhd-kb-article-form">
				<input type="hidden" id="mhd-kb-article-id" name="article_id" value="" />
				<p>
					<label for="mhd-kb-article-title"><?php esc_html_e( 'Title', 'myhelpdesk-chat' ); ?></label><br />
					<input type="text" id="mhd-kb-article-title" name="title" class="large-text" required />
				</p>
				<p>
					<label for="mhd-kb-article-category"><?php esc_html_e( 'Category', 'myhelpdesk-chat' ); ?></label><br />
					<select id="mhd-kb-article-category" name="category_id" style="width:100%;">
						<option value=""><?php esc_html_e( 'None', 'myhelpdesk-chat' ); ?></option>
						<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat->id ); ?>">
								<?php echo esc_html( $cat->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="mhd-kb-article-content"><?php esc_html_e( 'Content', 'myhelpdesk-chat' ); ?></label><br />
					<textarea id="mhd-kb-article-content" name="content" rows="10" class="large-text"></textarea>
				</p>
				<p>
					<label for="mhd-kb-article-status"><?php esc_html_e( 'Status', 'myhelpdesk-chat' ); ?></label><br />
					<select id="mhd-kb-article-status" name="status">
						<option value="draft"><?php esc_html_e( 'Draft', 'myhelpdesk-chat' ); ?></option>
						<option value="published"><?php esc_html_e( 'Published', 'myhelpdesk-chat' ); ?></option>
					</select>
				</p>
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Article', 'myhelpdesk-chat' ); ?>
				</button>
				<button type="button" id="mhd-kb-article-form-cancel" class="button">
					<?php esc_html_e( 'Cancel', 'myhelpdesk-chat' ); ?>
				</button>
			</form>
		</div>
	</div><!-- .mhd-kb-section (articles) -->

</div><!-- .wrap -->
