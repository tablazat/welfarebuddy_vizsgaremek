<script setup>
import { Primitive } from "reka-ui"
import { cn } from "@/lib/utils"
import { sidebarMenuButtonVariants } from "."

const props = defineProps({
  as: { default: 'button' },
  variant: { default: 'default' },
  size: { default: 'default' },
  isActive: { default: undefined },
  class: { default: undefined },
  asChild: { default: undefined },
})
</script>

<template>
  <Primitive
    data-slot="sidebar-menu-button"
    data-sidebar="menu-button"
    :data-size="size"
    :data-active="isActive"
    :class="cn(sidebarMenuButtonVariants({ variant, size }), props.class)"
    :as="as"
    :as-child="asChild"
    v-bind="$attrs"
  >
    <slot />
  </Primitive>
</template>
