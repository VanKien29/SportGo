<template>
  <span :class="className">
    {{ displayText }}
    <span class="animate-pulse duration-700">{{ cursor }}</span>
  </span>
</template>

<script>
import { ref, computed, watch, onUnmounted } from 'vue';

export default {
  name: 'Typewriter',
  props: {
    text: {
      type: [String, Array],
      required: true,
    },
    speed: {
      type: Number,
      default: 100,
    },
    cursor: {
      type: String,
      default: '|',
    },
    loop: {
      type: Boolean,
      default: false,
    },
    deleteSpeed: {
      type: Number,
      default: 50,
    },
    delay: {
      type: Number,
      default: 1500,
    },
    className: {
      type: String,
      default: '',
    },
  },
  setup(props) {
    const displayText = ref('');
    const currentIndex = ref(0);
    const isDeleting = ref(false);
    const textArrayIndex = ref(0);

    const textArray = computed(() => {
      return Array.isArray(props.text) ? props.text : [props.text];
    });

    const currentText = computed(() => {
      return textArray.value[textArrayIndex.value] || '';
    });

    let timeoutId = null;

    const startTyping = () => {
      if (!currentText.value) return;

      const currentSpeed = isDeleting.value ? props.deleteSpeed : props.speed;

      timeoutId = setTimeout(() => {
        if (!isDeleting.value) {
          if (currentIndex.value < currentText.value.length) {
            displayText.value += currentText.value[currentIndex.value];
            currentIndex.value++;
            startTyping();
          } else if (props.loop) {
            timeoutId = setTimeout(() => {
              isDeleting.value = true;
              startTyping();
            }, props.delay);
          }
        } else {
          if (displayText.value.length > 0) {
            displayText.value = displayText.value.slice(0, -1);
            startTyping();
          } else {
            isDeleting.value = false;
            currentIndex.value = 0;
            textArrayIndex.value = (textArrayIndex.value + 1) % textArray.value.length;
            startTyping();
          }
        }
      }, currentSpeed);
    };

    watch(() => props.text, () => {
      if (timeoutId) clearTimeout(timeoutId);
      displayText.value = '';
      currentIndex.value = 0;
      isDeleting.value = false;
      textArrayIndex.value = 0;
      startTyping();
    }, { immediate: true });

    onUnmounted(() => {
      if (timeoutId) clearTimeout(timeoutId);
    });

    return {
      displayText
    };
  },
};
</script>
