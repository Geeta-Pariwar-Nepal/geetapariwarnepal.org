<?php
/**
 * Template Name: Geeta Books — Premium
 * Description: Premium landing page for Saral Geeta and Triratna Geeta books.
 */

defined( 'ABSPATH' ) || exit;

get_header();

// Get the two featured Geeta books
$gp_books   = gp_get_geeta_books();
$gp_has_books = ! empty( $gp_books );
?>

<div id="primary" class="content-area gb-page">
	<main id="main" class="site-main">

		<?php if ( $gp_has_books ) : ?>
		<!-- ===== SECTION 1: PREMIUM HERO ===== -->
		<section class="gb-hero">
			<div class="gb-hero-decor gb-hero-decor--1"></div>
			<div class="gb-hero-decor gb-hero-decor--2"></div>
			<div class="gb-hero-decor gb-hero-decor--3"></div>
			<div class="ast-container">
				<div class="gb-hero-inner">
					<span class="gb-hero-icon" aria-hidden="true">📖</span>
					<span class="gb-hero-badge">आधिकारिक गीता पुस्तकहरू</span>
					<h1 class="gb-hero-title">नेपालमा आधिकारिक<br>गीता पुस्तकहरू</h1>
					<p class="gb-hero-subtitle">घरमै गीता अध्ययनका लागि उपलब्ध सरल गीता तथा त्रिरत्न गीता।</p>
					<div class="gb-hero-actions">
						<a href="#gb-products" class="gb-btn gb-btn-primary">🟢 अहिले अर्डर गर्नुहोस्</a>
						<a href="#gb-compare" class="gb-btn gb-btn-outline">⚪ पुस्तकहरू हेर्नुहोस्</a>
					</div>
					<div class="gb-hero-divider">
						<span class="gb-hero-divider-line"></span>
						<span class="gb-hero-divider-dot"></span>
						<span class="gb-hero-divider-line"></span>
					</div>
				</div>
			</div>
		</section>

		<!-- ===== SECTION 2: WHY THESE BOOKS ===== -->
		<section class="gb-why-section">
			<div class="ast-container">
				<div class="gb-section-header">
					<span class="gb-section-badge">किन यी पुस्तकहरू?</span>
					<h2 class="gb-section-title">प्रामाणिक, सरल र आध्यात्मिक</h2>
					<p class="gb-section-desc">गीता परिवार नेपालद्वारा प्रकाशित यी पुस्तकहरू वर्षौंको अनुभव र गहिरो आध्यात्मिक अध्ययनको परिणाम हुन्।</p>
				</div>
				<div class="gb-why-grid">
					<div class="gb-why-card">
						<div class="gb-why-icon" aria-hidden="true">📚</div>
						<h3 class="gb-why-title">प्रामाणिक सामग्री</h3>
						<p class="gb-why-text">शुद्ध उच्चारण र मौलिक श्लोकहरू सहितको प्रामाणिक गीता संस्करण। स्वामी श्रीगोविन्ददेव गिरि जीको मार्गदर्शनमा तयार पारिएको।</p>
					</div>
					<div class="gb-why-card">
						<div class="gb-why-icon" aria-hidden="true">🚚</div>
						<h3 class="gb-why-title">नेपालभर डेलिभरी</h3>
						<p class="gb-why-text">हाम्रा २३ डिपोहरू ७ प्रदेशमा फैलिएका छन्। तपाईंको नजिकको डिपोबाट सहज रूपमा पुस्तक प्राप्त गर्नुहोस्।</p>
					</div>
					<div class="gb-why-card">
						<div class="gb-why-icon" aria-hidden="true">🙏</div>
						<h3 class="gb-why-title">आध्यात्मिक अध्ययन</h3>
						<p class="gb-why-text">गीता पढौं, पढाऔं — ज्ञान बाँडौं, जीवनमा उतारौं। यी पुस्तकहरू दैनिक पाठ, अध्ययन र मननका लागि उत्तम छन्।</p>
					</div>
				</div>
			</div>
		</section>

		<!-- ===== SECTION 3: BOOKS GRID ===== -->
		<section id="gb-products" class="gb-products-section">
			<div class="ast-container">
				<div class="gb-section-header">
					<span class="gb-section-badge">हाम्रा पुस्तकहरू</span>
					<h2 class="gb-section-title">उपलब्ध गीता पुस्तकहरू</h2>
					<p class="gb-section-desc">गुणस्तरीय मुद्रण, सुन्दर बाइन्डिङ र शुद्ध सामग्री — आजै अर्डर गर्नुहोस्।</p>
				</div>
				<div class="gb-products-grid">
					<?php foreach ( $gp_books as $gp_product ) :
						$gp_id       = $gp_product->get_id();
						$gp_url      = $gp_product->get_permalink();
						$gp_name     = $gp_product->get_name();
						$gp_price    = $gp_product->get_price_html();
						$gp_image    = $gp_product->get_image( 'woocommerce_single', array( 'loading' => 'lazy' ) );
						$gp_short    = $gp_product->get_short_description() ?: $gp_product->get_description();
						$gp_stock    = $gp_product->get_stock_status();
						$gp_in_stock = 'instock' === $gp_stock;
					?>
					<div class="gb-product-card">
						<div class="gb-product-image-wrap">
							<a href="<?php echo esc_url( $gp_url ); ?>" tabindex="-1" aria-hidden="true">
								<?php echo $gp_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</a>
							<?php if ( $gp_in_stock ) : ?>
								<span class="gb-product-badge gb-product-badge--in">स्टकमा</span>
							<?php else : ?>
								<span class="gb-product-badge gb-product-badge--out">अर्डर मात्र</span>
							<?php endif; ?>
						</div>
						<div class="gb-product-body">
							<h3 class="gb-product-name"><?php echo esc_html( $gp_name ); ?></h3>
							<p class="gb-product-desc"><?php echo wp_kses_post( wp_trim_words( $gp_short, 20 ) ); ?></p>
							<div class="gb-product-meta">
								<span class="gb-product-price"><?php echo $gp_price; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<span class="gb-product-stock <?php echo $gp_in_stock ? 'gb-product-stock--in' : 'gb-product-stock--out'; ?>">
									<?php echo $gp_in_stock ? '✓ स्टकमा' : 'अर्डर गर्नुहोस्'; ?>
								</span>
							</div>
							<div class="gb-product-rating" aria-label="5 stars out of 5">
								<span class="gb-star" aria-hidden="true">★</span>
								<span class="gb-star" aria-hidden="true">★</span>
								<span class="gb-star" aria-hidden="true">★</span>
								<span class="gb-star" aria-hidden="true">★</span>
								<span class="gb-star" aria-hidden="true">★</span>
							</div>
							<div class="gb-product-actions">
								<a href="<?php echo esc_url( $gp_url . '?add-to-cart=' . $gp_id ); ?>" class="gb-btn gb-btn-primary gb-btn-block">🛒 कार्टमा राख्नुहोस्</a>
								<a href="<?php echo esc_url( gp_whatsapp_url( "म $gp_name अर्डर गर्न चाहन्छु।" ) ); ?>" target="_blank" rel="noopener noreferrer" class="gb-btn gb-btn-whatsapp gb-btn-block">💬 WhatsApp मार्फत अर्डर</a>
							</div>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<!-- ===== SECTION 4: BOOK COMPARISON ===== -->
		<section id="gb-compare" class="gb-compare-section">
			<div class="ast-container">
				<div class="gb-section-header">
					<span class="gb-section-badge">तुलना</span>
					<h2 class="gb-section-title">कुन पुस्तक तपाईंको लागि उपयुक्त?</h2>
					<p class="gb-section-desc">दुवै पुस्तकको विशेषता बुझेर आफ्नो आवश्यकता अनुसार छान्नुहोस्।</p>
				</div>
				<div class="gb-compare-table-wrap">
					<table class="gb-compare-table">
						<thead>
							<tr>
								<th class="gb-compare-feature">विशेषता</th>
								<th class="gb-compare-col gb-compare-col--1">सरल गीता</th>
								<th class="gb-compare-col gb-compare-col--2">त्रिरत्न गीता</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td class="gb-compare-feature">भाषा</td>
								<td class="gb-compare-col--1">नेपाली, हिन्दी</td>
								<td class="gb-compare-col--2">११ भाषाहरू</td>
							</tr>
							<tr>
								<td class="gb-compare-feature">कठिनाइ</td>
								<td class="gb-compare-col--1">सरल — सबैका लागि</td>
								<td class="gb-compare-col--2">मध्यम — अध्ययनरतका लागि</td>
							</tr>
							<tr>
								<td class="gb-compare-feature">उपयुक्त</td>
								<td class="gb-compare-col--1">नयाँ पाठक, दैनिक पाठ</td>
								<td class="gb-compare-col--2">गहिरो अध्ययन, उच्चारण अभ्यास</td>
							</tr>
							<tr>
								<td class="gb-compare-feature">मुद्रण</td>
								<td class="gb-compare-col--1">स्पष्ट र ठूलो फन्ट</td>
								<td class="gb-compare-col--2">शुद्ध उच्चारण चिह्न सहित</td>
							</tr>
							<tr>
								<td class="gb-compare-feature">पृष्ठ</td>
								<td class="gb-compare-col--1">१४४ पृष्ठ</td>
								<td class="gb-compare-col--2">२०८ पृष्ठ</td>
							</tr>
							<tr>
								<td class="gb-compare-feature">बाइन्डिङ</td>
								<td class="gb-compare-col--1">पेपरब्याक</td>
								<td class="gb-compare-col--2">पेपरब्याक / हार्डकभर / प्रिमियम</td>
							</tr>
							<tr>
								<td class="gb-compare-feature">अध्ययन उद्देश्य</td>
								<td class="gb-compare-col--1">दैनिक पाठ, सरल अध्ययन</td>
								<td class="gb-compare-col--2">शुद्ध उच्चारण, गहन मनन</td>
							</tr>
							<tr>
								<td class="gb-compare-feature">मूल्य</td>
								<td class="gb-compare-col--1">किफायती</td>
								<td class="gb-compare-col--2">संस्करण अनुसार</td>
							</tr>
							<tr class="gb-compare-recommend">
								<td class="gb-compare-feature">सिफारिस</td>
								<td class="gb-compare-col--1"><span class="gb-compare-badge">नयाँ पाठक</span></td>
								<td class="gb-compare-col--2"><span class="gb-compare-badge gb-compare-badge--gold">गहिरो अध्ययन</span></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</section>

		<!-- ===== SECTION 5: DETAILED BOOK INFO ===== -->
		<section class="gb-details-section">
			<div class="ast-container">
				<div class="gb-section-header">
					<span class="gb-section-badge">पुस्तक विवरण</span>
					<h2 class="gb-section-title">प्रत्येक पुस्तकको बारेमा विस्तृत जानकारी</h2>
					<p class="gb-section-desc">गुणस्तर, सामग्री र मुद्रणको बारेमा पूर्ण विवरण।</p>
				</div>
				<div class="gb-details-grid">
					<?php foreach ( $gp_books as $gp_product ) :
						$gp_id       = $gp_product->get_id();
						$gp_url      = $gp_product->get_permalink();
						$gp_name     = $gp_product->get_name();
						$gp_image    = $gp_product->get_image( 'woocommerce_single', array( 'loading' => 'lazy' ) );
						$gp_desc     = $gp_product->get_description();
					?>
					<div class="gb-details-card">
						<div class="gb-details-image">
							<a href="<?php echo esc_url( $gp_url ); ?>"><?php echo $gp_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
						</div>
						<div class="gb-details-content">
							<h3 class="gb-details-title"><?php echo esc_html( $gp_name ); ?></h3>
							<div class="gb-details-text"><?php echo wp_kses_post( $gp_desc ); ?></div>
							<div class="gb-details-features">
								<div class="gb-details-feature">
									<span class="gb-df-icon" aria-hidden="true">📄</span>
									<span class="gb-df-label">गुणस्तरीय मुद्रण</span>
								</div>
								<div class="gb-details-feature">
									<span class="gb-df-icon" aria-hidden="true">📖</span>
									<span class="gb-df-label">टिकाउ बाइन्डिङ</span>
								</div>
								<div class="gb-details-feature">
									<span class="gb-df-icon" aria-hidden="true">📝</span>
									<span class="gb-df-label">उच्च गुणस्तरको कागज</span>
								</div>
								<div class="gb-details-feature">
									<span class="gb-df-icon" aria-hidden="true">📐</span>
									<span class="gb-df-label">सुविधाजनक आकार</span>
								</div>
							</div>
							<a href="<?php echo esc_url( $gp_url ); ?>" class="gb-btn gb-btn-primary">यो पुस्तक हेर्नुहोस् →</a>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<!-- ===== SECTION 6: WHY BUY FROM US ===== -->
		<section class="gb-benefits-section">
			<div class="ast-container">
				<div class="gb-section-header">
					<span class="gb-section-badge">विश्वास</span>
					<h2 class="gb-section-title">किन गीता परिवार नेपालबाट किन्ने?</h2>
					<p class="gb-section-desc">हामी केवल पुस्तक बेच्दैनौं — हामी आध्यात्मिक यात्रामा साथ दिन्छौं।</p>
				</div>
				<div class="gb-benefits-grid">
					<div class="gb-benefits-card">
						<span class="gb-benefits-icon">✓</span>
						<h3>प्रामाणिक पुस्तकहरू</h3>
						<p>शुद्ध उच्चारण र मौलिक श्लोक सहितको प्रामाणिक संस्करण।</p>
					</div>
					<div class="gb-benefits-card">
						<span class="gb-benefits-icon">✓</span>
						<h3>विश्वसनीय संस्था</h3>
						<p>गीता परिवार नेपाल एक विश्वसनीय आध्यात्मिक संस्था हो।</p>
					</div>
					<div class="gb-benefits-card">
						<span class="gb-benefits-icon">✓</span>
						<h3>छिटो डेलिभरी</h3>
						<p>नेपालभर रहेका २३ डिपोहरूमार्फत सहज पुस्तक प्राप्ति।</p>
					</div>
					<div class="gb-benefits-card">
						<span class="gb-benefits-icon">✓</span>
						<h3>सुरक्षित भुक्तानी</h3>
						<p>डिपोमा भुक्तानी — अर्डर गर्दा कुनै शुल्क छैन।</p>
					</div>
					<div class="gb-benefits-card">
						<span class="gb-benefits-icon">✓</span>
						<h3>सहयोग उपलब्ध</h3>
						<p>कुनै समस्या भएमा इमेल र फोन मार्फत सहयोग।</p>
					</div>
					<div class="gb-benefits-card">
						<span class="gb-benefits-icon">✓</span>
						<h3>आध्यात्मिक मिशन</h3>
						<p>गीता पढौं, पढाऔं — ज्ञान बाँडौं, जीवनमा उतारौं।</p>
					</div>
				</div>
			</div>
		</section>

		<!-- ===== SECTION 7: FAQ ===== -->
		<section class="gb-faq-section">
			<div class="ast-container">
				<div class="gb-section-header">
					<span class="gb-section-badge">FAQ</span>
					<h2 class="gb-section-title">बारम्बार सोधिने प्रश्नहरू</h2>
					<p class="gb-section-desc">अर्डर गर्नुअघि यी प्रश्नहरू हेर्नुहोस्।</p>
				</div>
				<div class="gb-faq-list">
					<details class="gb-faq-item" open>
						<summary class="gb-faq-question">डेलिभरीमा कति समय लाग्छ?</summary>
						<div class="gb-faq-answer">
							<p>अर्डर पुष्टिपछि तपाईंले रोजेको डिपोमा पुस्तक रिजर्भ हुन्छ। तपाईं ७ दिनभित्र आफ्नो डिपोबाट पुस्तक लिन सक्नुहुन्छ।</p>
						</div>
					</details>
					<details class="gb-faq-item">
						<summary class="gb-faq-question">भुक्तानीका विकल्पहरू के के छन्?</summary>
						<div class="gb-faq-answer">
							<p>हाल डिपोमा नगद भुक्तानीको व्यवस्था छ। अर्डर गर्दा कुनै अग्रिम भुक्तानी आवश्यक छैन।</p>
						</div>
					</details>
					<details class="gb-faq-item">
						<summary class="gb-faq-question">के क्यास अन डेलिभरी उपलब्ध छ?</summary>
						<div class="gb-faq-answer">
							<p>हो, तपाईंले डिपोबाट पुस्तक लिँदा नगद भुक्तानी गर्न सक्नुहुन्छ। यो नै हालको एक मात्र भुक्तानी विकल्प हो।</p>
						</div>
					</details>
					<details class="gb-faq-item">
						<summary class="gb-faq-question">पुस्तक फेरबदल वा फिर्ताको के व्यवस्था छ?</summary>
						<div class="gb-faq-answer">
							<p>यदि पुस्तक खराब अवस्थामा छ भने, तपाईंले डिपोमा फेरबदल गर्न सक्नुहुन्छ। कृपया अर्डर गरेको ७ दिनभित्र सम्पर्क गर्नुहोस्।</p>
						</div>
					</details>
					<details class="gb-faq-item">
						<summary class="gb-faq-question">के सबै डिपोमा सबै पुस्तकहरू उपलब्ध छन्?</summary>
						<div class="gb-faq-answer">
							<p>सबै डिपोमा दुवै पुस्तकहरू उपलब्ध छन्। तर, संस्करण अनुसार केही डिपोमा सीमित मात्रा हुन सक्छ। अर्डर गर्दा स्टक जाँच गरिनेछ।</p>
						</div>
					</details>
				</div>
			</div>
		</section>

		<!-- ===== SECTION 8: CALL TO ACTION ===== -->
		<section class="gb-cta-section">
			<div class="gb-cta-bg"></div>
			<div class="ast-container">
				<div class="gb-cta-inner">
					<h2 class="gb-cta-title">आजै आफ्नो गीता घरमा ल्याउनुहोस्</h2>
					<p class="gb-cta-text">आध्यात्मिक ज्ञानको यात्रा सुरु गर्नुहोस्। गीता पढौं, पढाऔं — ज्ञान बाँडौं, जीवनमा उतारौं।</p>
					<a href="#gb-products" class="gb-btn gb-btn-primary gb-btn-large">🛒 अहिले अर्डर गर्नुहोस्</a>
				</div>
			</div>
		</section>

		<?php else : ?>
		<section class="gb-hero">
			<div class="ast-container">
				<div class="gb-hero-inner">
					<h1 class="gb-hero-title">पुस्तकहरू उपलब्ध छैनन्</h1>
					<p class="gb-hero-subtitle">कृपया पछि फेरि आउनुहोस्।</p>
				</div>
			</div>
		</section>
		<?php endif; ?>

	</main>
</div>

<?php get_footer(); ?>
