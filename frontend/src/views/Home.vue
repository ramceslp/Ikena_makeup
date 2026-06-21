<script setup>
import FeaturedNewsHero from '../components/home/FeaturedNewsHero.vue'
import LatestNewsGrid from '../components/home/LatestNewsGrid.vue'
import FeaturedCourses from '../components/home/FeaturedCourses.vue'
import FeaturedServices from '../components/home/FeaturedServices.vue'
import FeaturedProducts from '../components/home/FeaturedProducts.vue'
import NewsletterCta from '../components/home/NewsletterCta.vue'
</script>

<template>
  <div>
    <!-- 1. Featured news hero (replaces old courses HeroSection) -->
    <FeaturedNewsHero />

    <!-- 2. Latest news grid -->
    <LatestNewsGrid />

    <!-- 3. Featured courses (3 most-recent, link → /cursos) -->
    <FeaturedCourses />

    <!-- 4. Featured services (3 most-recent, link → /services) -->
    <FeaturedServices />

    <!-- 5. Featured products (3 most-recent, link → /products) -->
    <FeaturedProducts />

    <!-- 6. Newsletter CTA (unchanged) -->
    <NewsletterCta />
  </div>
</template>
