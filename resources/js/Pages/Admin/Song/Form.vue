<script setup>
import { useForm, Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import FormSection from "@/Components/FormSection.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { ref, computed } from "vue";

const props = defineProps({
    song: Object,
    categories: Array,
});

const groupedCategories = computed(() => {
    return props.categories.reduce((acc, category) => {
        const type = category.type || 'General';
        if (!acc[type]) acc[type] = [];
        acc[type].push(category);
        return acc;
    }, {});
});

const form = useForm({
    title: props.song?.title || "",
    alternative_title: props.song?.alternative_title || "",
    key: props.song?.key || "",
    tempo: props.song?.tempo || "",
    time_signature: props.song?.time_signature || "",
    lyric_author: props.song?.lyric_author || "",
    music_author: props.song?.music_author || "",
    copyright: props.song?.copyright || "",
    youtube_link: props.song?.youtube_link || "",
    content: props.song?.content || [{ type: "Verse 1", body: "" }],
    lyrics: props.song?.lyrics || "",
    categories: props.song?.categories ? props.song.categories.map(c => c.id) : [],
});

const sectionTypes = [
    "Verse 1",
    "Verse 2",
    "Verse 3",
    "Chorus",
    "Chorus 1",
    "Chorus 2",
    "Bridge",
    "Pre-Chorus",
    "Intro",
    "Outro",
    "Tag",
    "Ending",
];

const addSection = () => {
    form.content.push({ type: "Verse 1", body: "" });
};

const removeSection = (index) => {
    form.content.splice(index, 1);
};

const moveSection = (index, direction) => {
    if (direction === "up" && index > 0) {
        const temp = form.content[index];
        form.content[index] = form.content[index - 1];
        form.content[index - 1] = temp;
    } else if (direction === "down" && index < form.content.length - 1) {
        const temp = form.content[index];
        form.content[index] = form.content[index + 1];
        form.content[index + 1] = temp;
    }
};

const submit = () => {
    if (props.song) {
        form.put(route("admin.songs.update", props.song.id));
    } else {
        form.post(route("admin.songs.store"));
    }
};
</script>

<template>
    <AppLayout :title="props.song ? $t('Edit Song') : $t('Create Song')">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-text-main leading-tight">
                    {{ props.song ? $t("Edit Song") : $t("Create Song") }}
                </h2>
                <Link :href="route('admin.songs.index')"> {{ $t('Back to List') }} </Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <FormSection @submitted="submit">
                <template #title> {{ $t('Song Details') }} </template>
                <template #description>
                    {{ $t('General information about the song (metadata).') }}
                </template>

                <template #form>
                    <!-- Title -->
                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="title" :value="$t('Title')" />
                        <TextInput
                            id="title"
                            v-model="form.title"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            autofocus
                        />
                        <InputError :message="form.errors.title" class="mt-2" />
                    </div>

                    <!-- Alt Title -->
                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel
                            for="alternative_title"
                            :value="$t('Alternative Title')"
                        />
                        <TextInput
                            id="alternative_title"
                            v-model="form.alternative_title"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError
                            :message="form.errors.alternative_title"
                            class="mt-2"
                        />
                    </div>

                    <!-- Key & Tempo & Time Sig -->
                    <div class="col-span-6 sm:col-span-2">
                        <InputLabel for="key" :value="$t('Key')" />
                        <select
                            id="key"
                            v-model="form.key"
                            class="mt-1 block w-full border-border bg-surface text-text-main rounded-md shadow-sm"
                        >
                            <option value="">- {{ $t('Select Key') }} -</option>
                            <option v-for="k in ['C', 'C#', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'G#', 'A', 'Bb', 'B']" :key="k" :value="k">{{ k }}</option>
                        </select>
                        <InputError :message="form.errors.key" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-2">
                        <InputLabel for="tempo" :value="$t('Tempo (BPM)')" />
                        <TextInput
                            id="tempo"
                            v-model="form.tempo"
                            type="number"
                            class="mt-1 block w-full"
                        />
                         <InputError :message="form.errors.tempo" class="mt-2" />
                    </div>

                     <div class="col-span-6 sm:col-span-2">
                        <InputLabel for="time_signature" :value="$t('Time Sig')" />
                        <TextInput
                            id="time_signature"
                            v-model="form.time_signature"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="4/4"
                        />
                         <InputError :message="form.errors.time_signature" class="mt-2" />
                    </div>

                    <div class="col-span-6 border-t border-border my-4"></div>

                    <!-- Categories -->
                    <!-- Categories -->
                    <div class="col-span-6">
                        <InputLabel :value="$t('Categories')" />
                        
                        <div v-if="categories.length > 0" class="mt-4 space-y-6">
                            <div v-for="(group, type) in groupedCategories" :key="type">
                                <h4 class="text-sm font-semibold text-text-main capitalize mb-2 border-b border-border pb-1">
                                    {{ type }}
                                </h4>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <label v-for="category in group" :key="category.id" class="inline-flex items-center">
                                        <input 
                                            type="checkbox" 
                                            :value="category.id" 
                                            v-model="form.categories"
                                            class="rounded border-border text-primary shadow-sm focus:ring-primary bg-surface"
                                        >
                                        <span class="ml-2 text-sm text-text-main">{{ category.name }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div v-else class="mt-2 text-sm text-text-muted italic">
                            {{ $t('No categories available.') }}
                        </div>
                        <InputError :message="form.errors.categories" class="mt-2" />
                    </div>

                    <div class="col-span-6 border-t border-border my-4"></div>
                    
                    <!-- Credits -->
                   <div class="col-span-6 sm:col-span-3">
                        <InputLabel for="lyric_author" :value="$t('Lyric Author')" />
                        <TextInput
                            id="lyric_author"
                            v-model="form.lyric_author"
                            type="text"
                            class="mt-1 block w-full"
                        />
                    </div>
                     <div class="col-span-6 sm:col-span-3">
                        <InputLabel for="music_author" :value="$t('Music Author')" />
                        <TextInput
                            id="music_author"
                            v-model="form.music_author"
                            type="text"
                            class="mt-1 block w-full"
                        />
                    </div>
                    
                     <div class="col-span-6">
                        <InputLabel for="youtube_link" :value="$t('YouTube Link')" />
                        <TextInput
                            id="youtube_link"
                            v-model="form.youtube_link"
                            type="url"
                            class="mt-1 block w-full"
                        />
                    </div>

                </template>
            </FormSection>

            <div class="hidden sm:block" aria-hidden="true">
                <div class="py-8">
                    <div class="border-t border-border" />
                </div>
            </div>

            <!-- SONG CONTENT SECTION -->
            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-1">
                    <div class="px-4 sm:px-0">
                        <h3 class="text-lg font-medium leading-6 text-text-main">
                            {{ $t('Song Content') }}
                        </h3>
                        <p class="mt-1 text-sm text-text-muted">
                            {{ $t('Add lyrics and keys here. You can separate parts like Verse and Chorus.') }}
                        </p>
                        <div class="mt-4 p-3 bg-surface-variant rounded-md text-xs text-text-muted border border-border">
                            <span class="font-bold block mb-1">Tip:</span>
                            {{ $t('Use square brackets for chords to make them toggleable.') }} <br>
                            Example: <code class="bg-surface px-1 py-0.5 rounded border border-border">[C]</code> Amazing <code class="bg-surface px-1 py-0.5 rounded border border-border">[G]</code> Grace
                        </div>
                    </div>
                </div>

                <div class="mt-5 md:mt-0 md:col-span-2">
                    <div class="bg-surface shadow sm:rounded-md px-4 py-5 sm:p-6 space-y-4">
                        
                        <div v-for="(section, index) in form.content" :key="index" class="p-4 border border-border rounded-md bg-surface-variant relative group">
                             <!-- Controls -->
                             <div class="absolute top-2 right-2 flex space-x-2 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                                <button type="button" @click="moveSection(index, 'up')" :disabled="index === 0" class="text-text-muted hover:text-text-main disabled:opacity-30">↑</button>
                                <button type="button" @click="moveSection(index, 'down')" :disabled="index === form.content.length - 1" class="text-text-muted hover:text-text-main disabled:opacity-30">↓</button>
                                <button type="button" @click="removeSection(index)" class="text-red-500 hover:text-red-700">✕</button>
                             </div>

                             <div class="grid grid-cols-6 gap-4">
                                 <div class="col-span-2">
                                     <InputLabel :value="$t('Type')" class="mb-1 text-xs" />
                                     <select v-model="section.type" class="w-full text-sm border-border bg-surface rounded-md">
                                         <option v-for="type in sectionTypes" :key="type" :value="type">{{ type }}</option>
                                         <option value="Custom">Custom</option>
                                     </select>
                                 </div>
                                 <div class="col-span-6">
                                      <textarea 
                                        v-model="section.body" 
                                        rows="4" 
                                        class="w-full border-border bg-surface rounded-md text-text-main font-mono text-sm"
                                        :placeholder="$t('Enter lyrics (and chords)...')"
                                        ></textarea>
                                 </div>
                             </div>
                        </div>

                        <div class="flex justify-center">
                             <SecondaryButton type="button" @click="addSection">
                                + {{ $t('Add Section') }}
                             </SecondaryButton>
                        </div>

                    </div>
                    
                    <div class="flex items-center justify-end px-4 py-3 bg-surface-variant text-right sm:px-6 shadow sm:rounded-bl-md sm:rounded-br-md mt-4">
                         <div class="flex items-center gap-4">
                             <Link :href="route('admin.songs.index')" class="text-sm text-text-muted hover:text-text-main">{{ $t('Cancel') }}</Link>
                             <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="submit">
                                {{ $t('Save Song') }}
                            </PrimaryButton>
                         </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
