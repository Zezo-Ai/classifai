<?php

namespace Classifai\Taxonomy;

/**
 * The ClassifAI Keyword Taxonomy.
 *
 * Usage:
 *
 * ```php
 *
 * $taxonomy = new KeywordTaxonomy();
 * $taxonomy->register();
 *
 * ```
 */
class KeywordTaxonomy extends AbstractTaxonomy {

	/**
	 * Get the ClassifAI keyword taxonomy name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return WATSON_KEYWORD_TAXONOMY;
	}

	/**
	 * Get the ClassifAI keyword taxonomy label.
	 *
	 * @return string
	 */
	public function get_singular_label(): string {
		return esc_html__( 'Watson Keyword', 'classifai' );
	}

	/**
	 * Get the ClassifAI keyword taxonomy plural label.
	 *
	 * @return string
	 */
	public function get_plural_label(): string {
		return esc_html__( 'Watson Keywords', 'classifai' );
	}

	/**
	 * Get the ClassifAI keyword taxonomy visibility.
	 *
	 * @return bool
	 */
	public function get_visibility(): bool {
		return \Classifai\get_feature_enabled( 'keyword' ) &&
			\Classifai\get_feature_taxonomy( 'keyword' ) === $this->get_name();
	}
}
