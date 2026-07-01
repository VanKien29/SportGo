<template>
  <div class="partner-document-page-wrapper">
    <PublicNavbar />
    <PartnerDocumentModal
      :application-id="route.params.id"
      :document-id="route.params.documentId"
      :document-kind="route.query.type"
      :back-label="route.query.from === 'registration' ? 'Quay lại form nhập' : 'Quay lại hồ sơ'"
      @close="goBack"
      @signed="onSigned"
    />
  </div>
</template>

<script setup>
import { useRoute, useRouter } from 'vue-router';
import PublicNavbar from '../../components/PublicNavbar.vue';
import PartnerDocumentModal from './PartnerDocumentModal.vue';

const route = useRoute();
const router = useRouter();

function goBack() {
  if (route.query.from === 'registration') {
    router.push({ name: 'partner-application', query: { editDraft: route.params.id } });
    return;
  }
  router.push({ name: 'partner-application-detail', params: { id: route.params.id } });
}

function onSigned() {
  // Sau khi ký xong, reload lại data rồi để modal tự xử lý
}
</script>
