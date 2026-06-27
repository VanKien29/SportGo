<template>
  <PublicNavbar />
  <PartnerDocumentModal
    :application-id="route.params.id"
    :document-id="route.params.documentId"
    :document-kind="route.query.type"
    @close="goBack"
    @signed="onSigned"
  />
</template>

<script setup>
import { useRoute, useRouter } from 'vue-router';
import PublicNavbar from '../../components/PublicNavbar.vue';
import PartnerDocumentModal from './PartnerDocumentModal.vue';

const route = useRoute();
const router = useRouter();

function goBack() {
  router.push({ name: 'partner-application-detail', params: { id: route.params.id } });
}

function onSigned() {
  // Sau khi ký xong, reload lại data rồi để modal tự xử lý
}
</script>
