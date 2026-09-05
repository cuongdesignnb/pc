<script setup lang="ts">
const {
  siteLogo,
  siteLogoWhite,
  siteName,
  siteTagline,
  siteDescription,
  siteHotline,
  siteEmail,
  siteAddress,
  businessHours,
  socialFacebook,
  socialYoutube,
  socialTiktok,
  socialZalo,
  socialInstagram,
  getString,
  getBoolean,
} = useSettings()

const year = new Date().getFullYear()

const socialLinks = computed(() => [
  { label: 'Facebook', href: socialFacebook.value, mark: 'f' },
  { label: 'YouTube', href: socialYoutube.value, mark: '▶' },
  { label: 'TikTok', href: socialTiktok.value, mark: '♪' },
  { label: 'Zalo', href: socialZalo.value.startsWith('http') ? socialZalo.value : (socialZalo.value ? `https://zalo.me/${socialZalo.value.replace(/\D/g, '')}` : ''), mark: 'z' },
  { label: 'Instagram', href: socialInstagram.value, mark: '◎' },
].filter(link => link.href))

const paymentMethods = computed(() => {
  const methods: { label: string; detail: string }[] = []
  const bankName = getString('payment_bank_name')
  if (bankName) methods.push({ label: 'SePay / VietQR', detail: bankName })
  if (getBoolean('payment_cod_enabled', true)) methods.push({ label: 'COD', detail: 'Thanh toán khi nhận hàng' })
  return methods
})

const zaloHref = computed(() => {
  if (!socialZalo.value) return ''
  return socialZalo.value.startsWith('http') ? socialZalo.value : `https://zalo.me/${socialZalo.value.replace(/\D/g, '')}`
})
</script>

<template>
  <footer class="site-footer">
    <div class="pc-container site-footer-grid">
      <section class="site-footer-about">
        <NuxtLink to="/" class="site-footer-logo">
          <img v-if="siteLogoWhite || siteLogo" :src="siteLogoWhite || siteLogo" :alt="siteName">
          <span v-else class="site-footer-mark">PC</span>
          <span><strong>{{ siteName }}</strong><small>{{ siteTagline || 'Build Your Dream' }}</small></span>
        </NuxtLink>
        <p>{{ siteDescription || siteTagline }}</p>
        <div class="site-footer-socials" aria-label="Mạng xã hội">
          <a v-for="link in socialLinks" :key="link.label" :href="link.href" target="_blank" rel="noopener noreferrer" :aria-label="link.label">
            {{ link.mark }}
          </a>
        </div>
        <div v-if="siteHotline || siteEmail" class="site-footer-contact">
          <a v-if="siteHotline" :href="`tel:${siteHotline.replace(/\s/g, '')}`">{{ siteHotline }}</a>
          <a v-if="siteEmail" :href="`mailto:${siteEmail}`">{{ siteEmail }}</a>
        </div>
      </section>

      <section>
        <h3>VỀ {{ siteName }}</h3>
        <ul>
          <li><NuxtLink to="/gioi-thieu">Giới thiệu</NuxtLink></li>
          <li><NuxtLink to="/tin-tuc">Tin tức</NuxtLink></li>
          <li><NuxtLink to="/lien-he">Liên hệ</NuxtLink></li>
          <li><NuxtLink to="/cau-hinh">PC Builder</NuxtLink></li>
        </ul>
      </section>

      <section>
        <h3>CHÍNH SÁCH</h3>
        <ul>
          <li><NuxtLink to="/bao-hanh">Chính sách bảo hành</NuxtLink></li>
          <li><NuxtLink to="/van-chuyen">Chính sách vận chuyển</NuxtLink></li>
          <li><NuxtLink to="/thanh-toan">Chính sách thanh toán</NuxtLink></li>
        </ul>
      </section>

      <section>
        <h3>HỖ TRỢ KHÁCH HÀNG</h3>
        <ul>
          <li><NuxtLink to="/lien-he">Trung tâm hỗ trợ</NuxtLink></li>
          <li><NuxtLink to="/gioi-thieu">Hướng dẫn mua hàng</NuxtLink></li>
          <li><NuxtLink to="/thanh-toan">Hướng dẫn trả góp</NuxtLink></li>
          <li><NuxtLink to="/tai-khoan">Kiểm tra đơn hàng</NuxtLink></li>
          <li><NuxtLink to="/bao-hanh">Hướng dẫn bảo hành</NuxtLink></li>
        </ul>
      </section>

      <section>
        <h3>THANH TOÁN</h3>
        <div class="site-footer-payments">
          <span v-for="method in paymentMethods" :key="method.label" :title="method.detail">{{ method.label }}</span>
        </div>
        <p v-if="siteAddress" class="site-footer-address">{{ siteAddress }}</p>
        <p v-if="businessHours" class="site-footer-hours">{{ businessHours }}</p>
      </section>

      <FooterNewsletter />
    </div>

    <div class="pc-container site-footer-bottom">
      <span>© {{ year }} {{ siteName }}. All rights reserved.</span>
      <span v-if="siteHotline">Hotline: {{ siteHotline }}</span>
    </div>

    <a v-if="zaloHref" :href="zaloHref" target="_blank" rel="noopener noreferrer" class="site-chat-button">
      <span aria-hidden="true">●</span> Chat với chúng tôi!
    </a>
  </footer>
</template>
