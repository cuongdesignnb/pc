<script setup lang="ts">
import type { HomepageTestimonial } from '~/types/homepage'

defineProps<{ testimonials: HomepageTestimonial[] }>()

function initials(name: string): string {
  return name.split(/\s+/).filter(Boolean).slice(-2).map(part => part.charAt(0)).join('').toUpperCase() || 'KH'
}
</script>

<template>
  <section v-if="testimonials.length" class="home-section home-testimonials-section">
    <div class="pc-container">
      <div class="home-section-heading"><h2>KHÁCH HÀNG NÓI VỀ CHÚNG TÔI</h2><NuxtLink to="/lien-he">Xem tất cả <span aria-hidden="true">›</span></NuxtLink></div>
      <div class="home-testimonials-grid">
        <article v-for="testimonial in testimonials.slice(0, 4)" :key="testimonial.id" class="home-testimonial-card">
          <div class="home-testimonial-header">
            <span class="home-testimonial-avatar">
              <img v-if="testimonial.avatar" :src="testimonial.avatar" :alt="testimonial.name" loading="lazy">
              <span v-else aria-hidden="true">{{ initials(testimonial.name) }}</span>
            </span>
            <span><strong>{{ testimonial.name }}</strong><small v-if="testimonial.city">{{ testimonial.city }}</small></span>
          </div>
          <div class="home-testimonial-stars" :aria-label="`${testimonial.rating} trên 5 sao`">
            <span v-for="star in 5" :key="star" :class="{ 'is-muted': star > testimonial.rating }" aria-hidden="true">★</span>
          </div>
          <p>“{{ testimonial.body }}”</p>
          <small v-if="testimonial.verified_purchase" class="home-testimonial-verified">Đã mua hàng</small>
        </article>
      </div>
    </div>
  </section>
</template>
