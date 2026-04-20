<script setup lang="ts">
defineOptions({
	inheritAttrs: false,
})

const props = defineProps({
	icon: { type: String, required: false },
	label: { type: String, required: false },
})
</script>

<template>
	<section class="panel" v-bind="$attrs">
		<div v-if="props.label" class="panel--header">
			<Icon v-if="props.icon" :name="props.icon" />
			<h2 class="panel--header--title"> {{ props.label }} </h2>
		</div>

		<div class="panel--content">
			<slot />
		</div>
	</section>
</template>

<style scoped lang="scss">
@use "@/assets/style/components/panel";
</style>