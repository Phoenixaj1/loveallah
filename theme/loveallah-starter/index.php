<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main class="la-main">
	<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
		<article class="la-article">
			<h1><?php the_title(); ?></h1>
			<div class="la-prose"><?php the_content(); ?></div>
		</article>
	<?php endwhile; endif; ?>
</main>
<?php get_footer();
