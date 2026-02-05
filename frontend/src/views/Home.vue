<script setup>
import { inject } from 'vue'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseCard from '../components/ui/BaseCard.vue'
import BaseModal from '../components/ui/BaseModal.vue'
import GoldChart from '../components/GoldChart.vue'
import PublicLayout from '../layouts/PublicLayout.vue'
import { useI18n } from 'vue-i18n'
import {
  BuildingLibraryIcon, ShieldCheckIcon, DocumentCheckIcon, TruckIcon, ScaleIcon,
  ChartBarIcon, UserGroupIcon, GlobeAltIcon
} from '@heroicons/vue/24/outline'

const { t, tm } = useI18n()
const openModal = inject('openAccreditationModal')

const scrollToProtocol = () => {
    const el = document.getElementById('protocol')
    if (el) el.scrollIntoView({ behavior: 'smooth' })
}
</script>

<template>
  <div class="relative overflow-hidden">
    <!-- Hero Section -->
    <section class="relative min-h-[90vh] flex items-center justify-center pt-20">
      <!-- Background Effects -->
      <div class="absolute inset-0 z-0 pointer-events-none">
        <div class="absolute top-1/4 left-[30%] w-96 h-96 bg-gold-400/50 rounded-full blur-[128px] animate-float-diagonal"></div>
        <div class="absolute bottom-1/4 right-[20%] w-96 h-96 bg-gold-600/20 rounded-full blur-[128px] animate-float-diagonal-reverse"></div>
      </div>

      <div 
        class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center"
        v-motion
        :initial="{ opacity: 0, scale: 0.8 }"
        :enter="{ opacity: 1, scale: 1, transition: { duration: 1000, type: 'spring', stiffness: 50, damping: 15 } }"
      >
        <div class="inline-flex items-center px-4 py-2 rounded-full border border-gold-500/30 bg-gold-500/10 mb-8 backdrop-blur-sm">
          <span class="w-2 h-2 rounded-full bg-gold-400 mr-2 shadow-[0_0_10px_rgba(212,175,55,0.5)]"></span>
          <span class="text-gold-200 text-sm font-medium tracking-wide Uppercase">{{ t('hero.badge') }}</span>
        </div>

        <h1 class="font-kanit text-5xl sm:text-7xl font-black text-white mb-6 leading-tight uppercase tracking-wide">
          {{ t('hero.title_prefix') }} <br />
          <span class="gold-text-gradient drop-shadow-2xl">{{ t('hero.title_highlight') }}</span>
        </h1>

        <p class="text-xl text-gray-400 max-w-2xl mx-auto mb-10 leading-relaxed font-light" v-html="t('hero.subtitle')">
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center space-y-4 sm:space-y-0 sm:space-x-6">
          <BaseButton size="lg" variant="primary" @click="openModal">
            {{ t('hero.cta_primary') }}
          </BaseButton>
          <BaseButton size="lg" variant="secondary" @click="scrollToProtocol">
             {{ t('hero.cta_secondary') }}
          </BaseButton>
        </div>

        <!-- 3D Gold Ingot (Horizontal Minted - Compact) -->
        <div class="mt-12 mb-12 relative w-[320px] h-[180px] mx-auto perspective-1000 hidden sm:block z-10 hover:scale-105 transition-transform duration-700 cursor-pointer group">
             <!-- Bar Body -->
             <div class="relative w-full h-full bg-gradient-to-tr from-[#C59B25] via-[#FFD700] to-[#E1B32A] rounded-xl shadow-[20px_20px_60px_rgba(0,0,0,0.6),inset_0_1px_1px_rgba(255,255,255,0.8)] transform rotate-y-[-10deg] rotate-x-[10deg] transition-transform duration-700 group-hover:rotate-y-[0deg] group-hover:rotate-x-[0deg]">
                  
                  <!-- Bevel/Edge Highlight -->
                  <div class="absolute inset-1 rounded-lg border border-gold-300/50 bg-gradient-to-br from-[#FFEC8B] via-[#D4AF37] to-[#8B6508]"></div>
                  
                  <!-- Inner Face (Minted Area) -->
                  <div class="absolute inset-3 rounded-[8px] bg-gradient-to-b from-[#FFD700] via-[#F5D061] to-[#DAA520] shadow-[inset_2px_2px_5px_rgba(0,0,0,0.3),inset_-1px_-1px_2px_rgba(255,255,255,0.5)] flex items-center justify-between px-4 border border-black/5">
                      
                      <!-- Left: Brand -->
                      <div class="flex flex-col items-center flex-1">
                          <img src="/logo.png" class="w-8 h-8 opacity-60 mix-blend-multiply grayscale mb-1 invert" />
                          <span class="font-kanit font-black text-[#5C4208] text-sm tracking-[0.1em] opacity-80" style="text-shadow: 0 1px 0 rgba(255,255,255,0.4)">ATLAS</span>
                      </div>
                      
                      <!-- Center: Purity -->
                      <div class="flex flex-col items-center justify-center border-x border-[#5C4208]/10 px-4 h-[80%] flex-shrink-0 animate-float-slow">
                          <span class="font-oswald font-bold text-[#705208] text-4xl tracking-tighter" style="text-shadow: -1px -1px 1px rgba(0,0,0,0.2), 1px 1px 1px rgba(255,255,255,0.5)">999.9</span>
                          <span class="font-serif font-bold text-[#5C4208] text-[9px] uppercase tracking-widest mt-1 opacity-60">Fine Gold</span>
                      </div>

                      <!-- Right: Weight/Serial -->
                      <div class="flex flex-col items-center flex-1 animate-float-slow" style="animation-delay: 1s;">
                          <span class="font-mono text-[#5C4208] font-bold text-base opacity-80 mb-1">1000g</span>
                          <div class="px-1.5 py-0.5 border border-[#5C4208]/30 rounded-[2px] text-[8px] font-mono text-[#5C4208]/70 bg-[#5C4208]/5 shadow-[inset_0_1px_2px_rgba(0,0,0,0.1)] whitespace-nowrap">AA 849201</div>
                      </div>
                  </div>

                  <!-- Shine Overlay -->
                  <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none mix-blend-soft-light" style="background-size: 200% 200%;"></div>
             </div>
        </div>
      </div>
    </section>

    <!-- Trust & Status (Stats) -->
    <section class="py-24 relative overflow-hidden bg-dark-800 border-y border-white/5">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="text-center mb-16" v-motion-slide-visible-once-bottom>
           <h2 class="text-3xl sm:text-4xl font-kanit font-bold text-white uppercase tracking-widest inline-flex items-center gap-3">
              <span class="w-1.5 h-8 bg-gold-500 rounded-full shadow-[0_0_15px_rgba(212,175,55,0.8)]"></span>
              {{ t('trust.title') }}
           </h2>
        </div>
        
        <!-- Stats Grid (Text Only 3D) -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-8 mb-20">
          <div 
            v-for="(stat, index) in tm('trust.stats')" 
            :key="index" 
            class="text-center p-2 group"
            v-motion
            :initial="{ opacity: 0, scale: 0.5 }"
            :visibleOnce="{ opacity: 1, scale: 1, transition: { delay: index * 100, duration: 600, type: 'spring' } }"
          >
            <!-- Glow Text Effect -->
            <div class="text-5xl sm:text-6xl font-black gold-text-gradient mb-2 transform group-hover:scale-110 transition-transform duration-300 drop-shadow-[0_0_15px_rgba(212,175,55,0.4)]">
              {{ stat.value }}
            </div>
            <div class="text-gray-400 text-sm font-medium uppercase tracking-widest group-hover:text-gold-200 transition-colors">{{ stat.label }}</div>
          </div>
        </div>

        <!-- Features Cards (3D Block Effect) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div 
            v-for="(feat, index) in tm('trust.features')" 
            :key="index"
            class="h-full relative group rounded-2xl"
            v-motion
            :initial="{ opacity: 0, x: -50 }"
            :visibleOnce="{ opacity: 1, x: 0, transition: { delay: index * 200, duration: 800, type: 'spring' } }"
          >
            <!-- 3D Card Container -->
            <div class="h-full p-6 rounded-2xl bg-gradient-to-b from-white/5 to-white/0 border-t border-white/10 shadow-[0_10px_20px_rgba(0,0,0,0.3),inset_0_1px_0_rgba(255,255,255,0.1)] group-hover:border-gold-500/50 group-hover:from-white/10 group-hover:bg-white/5 group-hover:-translate-y-2 group-hover:shadow-[0_20px_30px_rgba(212,175,55,0.15),inset_0_1px_0_rgba(255,255,255,0.2)] transition-all duration-300 relative overflow-hidden">
               <!-- 3D Depth Inner Shadow -->
               <div class="absolute inset-0 rounded-2xl shadow-[inset_0_-4px_0_rgba(0,0,0,0.3)] pointer-events-none z-0"></div>
               
               <div class="relative z-10">
                 <div class="mb-4 text-gold-400 inline-block p-2 bg-gold-400/10 rounded-lg group-hover:scale-110 group-hover:bg-gold-400/20 group-hover:text-gold-300 transition-all duration-300">
                    <ShieldCheckIcon v-if="index === 0" class="w-8 h-8"/>
                    <BuildingLibraryIcon v-if="index === 1" class="w-8 h-8"/>
                    <DocumentCheckIcon v-if="index === 2" class="w-8 h-8"/>
                    <GlobeAltIcon v-if="index === 3" class="w-8 h-8"/>
                 </div>
                 <h3 class="text-lg font-semibold text-white mb-2 font-kanit uppercase tracking-wide group-hover:text-gold-100 transition-colors">{{ feat.title }}</h3>
                 <p class="text-gray-400 text-sm leading-relaxed group-hover:text-gray-200 transition-colors">{{ feat.desc }}</p>
               </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Offer (Premium List Layout) -->
    <section class="py-32 relative bg-dark-900">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="text-center mb-24" v-motion-slide-visible-once-bottom>
          <h2 class="text-3xl sm:text-4xl font-kanit font-bold text-white uppercase tracking-widest inline-flex items-center gap-3">
              <span class="w-1.5 h-8 bg-gold-500 rounded-full shadow-[0_0_15px_rgba(212,175,55,0.8)]"></span>
              {{ t('offer.title') }}
          </h2>
        </div>
        
        <div class="space-y-0">
          <div 
            v-for="(card, index) in tm('offer.cards')" 
            :key="index"
            class="group relative py-8 border-b border-white/5 hover:border-gold-500/30 transition-colors duration-500 cursor-default"
            v-motion
            :initial="{ opacity: 0, x: -50 }"
            :visibleOnce="{ opacity: 1, x: 0, transition: { delay: index * 100, duration: 800 } }"
          >
            <!-- Hover Background Gradient -->
            <div class="absolute inset-0 bg-gradient-to-r from-gold-500/0 via-gold-500/5 to-gold-500/0 opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>

            <div class="flex flex-col md:flex-row md:items-baseline md:gap-12 relative z-10">
               <!-- Number -->
               <div class="hidden md:block font-oswald text-2xl text-gray-700 group-hover:text-gold-500 transition-colors duration-300 w-16 text-right">
                  0{{ index + 1 }}
               </div>

               <!-- Content -->
               <div class="flex-1">
                  <div class="flex items-center justify-between mb-3">
                     <h3 class="text-xl sm:text-2xl font-kanit font-bold text-white uppercase tracking-wide group-hover:text-gold-200 transition-colors duration-300">
                        {{ card.title }}
                     </h3>
                     <!-- Decor Icon -->
                     <div class="w-10 h-10 rounded-full border border-white/10 flex items-center justify-center group-hover:border-gold-500 group-hover:bg-gold-500 group-hover:text-dark-900 text-gray-500 transition-all duration-300">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                           <path d="M4 8l8-4 8 4-8 4-8-4z" opacity="0.9" />
                           <path d="M4 10v6l8 4 8-4v-6l-8 4-8-4z" />
                        </svg>
                     </div>
                  </div>
                   <p class="text-gray-400 text-sm max-w-2xl group-hover:text-gray-300 transition-colors duration-300 pl-0 md:pl-0">
                      {{ card.desc }}
                   </p>
               </div>
            </div>
            
            <!-- Bottom Running Line on Hover -->
            <div class="absolute bottom-0 left-0 h-[1px] bg-gradient-to-r from-transparent via-gold-500 to-transparent w-full scale-x-0 group-hover:scale-x-100 transition-transform duration-700 ease-out origin-left"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- Investment Chart -->
    <GoldChart />

    <!-- Timeline (Glamorous Redesign) -->
    <section id="protocol" class="py-32 relative overflow-hidden">
      <!-- Background & Ambient Effects -->
      <div class="absolute inset-0 bg-dark-900">
         <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_var(--tw-gradient-stops))] from-gold-900/20 via-dark-900 to-dark-900"></div>
         <!-- Subtle Grid Pattern -->
         <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(#D4AF37 1px, transparent 1px); background-size: 40px 40px;"></div>
         <!-- Glow Orb -->
         <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-gold-600/5 blur-[100px] rounded-full pointer-events-none"></div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
         <div class="grid grid-cols-1 lg:grid-cols-12 gap-16 items-start">
            
            <!-- Timeline Column (Left - 7 cols) -->
            <div class="lg:col-span-7">
              <h2 class="text-3xl sm:text-4xl font-kanit font-bold text-white mb-12 uppercase tracking-widest inline-flex items-center gap-3" v-motion-slide-visible-once-bottom>
                  <span class="w-1.5 h-8 bg-gold-500 rounded-full shadow-[0_0_15px_rgba(212,175,55,0.8)]"></span>
                  {{ t('timeline.title') }}
              </h2>
              
              <div class="relative h-full">
                <!-- Base Vertical Guide Line (Background Track) -->
                <div class="absolute left-0 top-0 bottom-0 w-[2px] bg-white/5"></div>

                <!-- SINGLE GLOBAL ARROW (Moves down entire list) -->
                <div class="absolute left-[-11px] top-0 bottom-0 w-[24px] pointer-events-none z-30 overflow-hidden">
                    <div class="absolute -top-[200px] left-0 w-full h-[200px] animate-global-arrow">
                         <!-- The Gold Tail -->
                         <div class="absolute bottom-[20px] left-[11px] w-[2px] h-[180px] bg-gradient-to-t from-gold-500 via-gold-500/50 to-transparent"></div>
                         
                         <!-- The Arrow Head -->
                         <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-[24px] h-[24px] text-white absolute bottom-0 left-0 drop-shadow-[0_0_10px_rgba(255,255,255,1)]">
                            <path fill-rule="evenodd" d="M12.53 16.28a.75.75 0 0 1-1.06 0l-7.5-7.5a.75.75 0 0 1 1.06-1.06L12 14.69l6.97-6.97a.75.75 0 1 1 1.06 1.06l-7.5 7.5Z" clip-rule="evenodd" />
                         </svg>
                    </div>
                </div>

                <div class="space-y-0">
                  <div 
                      v-for="(step, index) in tm('timeline.steps')" 
                      :key="index" 
                      class="group relative pl-8 py-6 transition-all duration-500 cursor-default overflow-hidden"
                      v-motion
                      :initial="{ opacity: 0, x: -20 }"
                      :visibleOnce="{ opacity: 1, x: 0, transition: { delay: index * 100 } }"
                  >
                    <!-- Small Connector Dot -->
                    <div 
                      class="absolute left-[-5px] top-1/2 -translate-y-1/2 w-2.5 h-2.5 rounded-full bg-dark-900 border border-white/10 z-20 transition-colors duration-300 animate-scan-dot"
                      :style="{ animationDelay: `${(index * 0.38 - 0.95).toFixed(2)}s` }"
                    ></div>

                    <!-- Content -->
                    <div class="relative z-10 flex items-center">
                        <div 
                          class="mr-6 font-oswald text-2xl group-hover:text-gold-500 transition-colors duration-300 shadow-gold tabular-nums animate-text-scan"
                          :style="{ animationDelay: `${(index * 0.38 - 0.95).toFixed(2)}s` }"
                        >
                            0{{ index + 1 }}
                        </div>
                        
                        <h4 
                          class="text-xl sm:text-2xl font-kanit font-bold text-white uppercase tracking-wide group-hover:translate-x-2 transition-transform duration-300 animate-text-scan"
                          :style="{ animationDelay: `${(index * 0.38 - 0.95).toFixed(2)}s` }"
                        >
                            {{ step }}
                        </h4>
                    </div>
                  </div>
                </div>
              </div>


            </div>
            
            <!-- Mechanics Column (Right - 5 cols) -->
            <div class="lg:col-span-5 relative" v-motion-slide-visible-once-right>
               <!-- Sticky Container -->
               <div class="sticky top-24">
                   <!-- Premium Glass Card -->
                   <div class="relative rounded-2xl overflow-hidden border border-gold-500/20 bg-dark-800/80 backdrop-blur-xl shadow-[0_20px_50px_rgba(0,0,0,0.5)]">
                       <!-- Decorative Header line -->
                       <div class="h-1 w-full bg-gradient-to-r from-gold-600 via-gold-400 to-gold-600"></div>
                       
                       <div class="p-8">
                           <h3 class="text-2xl font-kanit font-bold text-white mb-8 uppercase tracking-wide text-center">
                               {{ t('timeline.mechanics_title') }}
                           </h3>
                           
                           <div class="grid grid-cols-1 gap-4">
                               <div 
                                   v-for="(mech, index) in tm('timeline.mechanics')" 
                                   :key="index"
                                   class="flex items-center p-4 rounded-lg bg-white/5 border border-white/5 hover:border-gold-500 hover:bg-gold-500/20 hover:scale-[1.02] hover:shadow-[0_0_20px_rgba(212,175,55,0.2)] transition-all duration-300 group cursor-pointer"
                               >
                                   <div class="w-10 h-10 rounded-full bg-dark-900 border border-gold-500/20 flex items-center justify-center mr-4 group-hover:border-gold-500 group-hover:shadow-[0_0_10px_rgba(212,175,55,0.4)] transition-all">
                                       <svg v-if="index === 0" class="w-5 h-5 text-gold-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                       <svg v-if="index === 1" class="w-5 h-5 text-gold-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                       <svg v-if="index === 2" class="w-5 h-5 text-gold-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                       <svg v-if="index === 3" class="w-5 h-5 text-gold-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                       <svg v-if="index === 4" class="w-5 h-5 text-gold-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-linecap="round" stroke-linejoin="round"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                   </div>
                                   <span class="text-gray-300 font-medium group-hover:text-white transition-colors">{{ mech }}</span>
                               </div>
                           </div>
                       </div>
                       
                       <!-- Footer Detail -->
                       <div class="bg-gold-500/5 p-4 text-center border-t border-white/5">
                           <span class="text-xs text-gold-500/60 uppercase tracking-widest font-bold">{{ t('timeline.protocol') }}</span>
                       </div>
                   </div>
               </div>
            </div>
         </div>
      </div>
    </section>


    <!-- Quality, Advantages & Conditions (Premium Grid) -->
    <section class="py-32 relative overflow-hidden">
      <!-- Background Ambient -->
      <div class="absolute inset-0 bg-dark-900 pointer-events-none">
          <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-gold-500/5 blur-[120px] rounded-full"></div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
           
           <!-- Column 1: Origin & Quality -->
           <div class="group" v-motion-slide-visible-once-bottom>
              <div class="relative h-full p-8 rounded-3xl bg-gradient-to-b from-gold-500/10 to-dark-800/50 backdrop-blur-xl border border-gold-500/30 shadow-[0_0_20px_rgba(212,175,55,0.1)] hover:border-gold-500/60 hover:shadow-[0_0_50px_rgba(212,175,55,0.25)] transition-all duration-500 hover:-translate-y-2">
                  <!-- Header -->
                  <div class="mb-8 relative">
                      <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-gold-400 to-gold-600 flex items-center justify-center mb-6 shadow-lg shadow-gold-500/20 group-hover:scale-110 transition-transform duration-500">
                          <ShieldCheckIcon class="w-6 h-6 text-dark-900" />
                      </div>
                      <h3 class="text-2xl font-kanit font-bold text-white uppercase tracking-wide group-hover:text-gold-300 transition-colors">
                          {{ t('quality.title') }}
                      </h3>
                      <div class="absolute -bottom-4 left-0 w-12 h-1 bg-gold-500 rounded-full group-hover:w-full transition-all duration-700 ease-out"></div>
                  </div>

                  <!-- List -->
                  <ul class="space-y-6">
                      <li v-for="(item, index) in tm('quality.items')" :key="index" class="relative pl-8">
                          <!-- Gold Bullet -->
                          <span class="absolute left-0 top-1.5 w-2 h-2 rounded-full bg-gold-500 shadow-[0_0_8px_rgba(212,175,55,0.8)]"></span>
                          
                          <h4 class="text-base font-bold text-gray-200 mb-1 group-hover:text-white transition-colors">{{ item.title }}</h4>
                          <p class="text-sm text-gray-400 group-hover:text-gray-300 transition-colors">{{ item.desc }}</p>
                      </li>
                  </ul>
              </div>
           </div>

           <!-- Column 2: Market Advantages -->
           <div class="group" v-motion-slide-visible-once-bottom :delay="100">
              <div class="relative h-full p-8 rounded-3xl bg-gradient-to-b from-gold-500/10 to-dark-800/50 backdrop-blur-xl border border-gold-500/30 shadow-[0_0_20px_rgba(212,175,55,0.1)] hover:border-gold-500/60 hover:shadow-[0_0_50px_rgba(212,175,55,0.25)] transition-all duration-500 hover:-translate-y-2">
                  <!-- Header -->
                  <div class="mb-8 relative">
                      <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-white/10 to-white/5 border border-white/10 flex items-center justify-center mb-6 group-hover:bg-gold-500/20 group-hover:border-gold-500/50 transition-all duration-500">
                          <ScaleIcon class="w-6 h-6 text-gold-400" />
                      </div>
                      <h3 class="text-2xl font-kanit font-bold text-white uppercase tracking-wide group-hover:text-gold-300 transition-colors">
                          {{ t('advantages.title') }}
                      </h3>
                      <div class="absolute -bottom-4 left-0 w-12 h-1 bg-gray-600 rounded-full group-hover:w-full group-hover:bg-gold-500 transition-all duration-700 ease-out"></div>
                  </div>

                  <!-- List -->
                  <ul class="space-y-6">
                      <li v-for="(item, index) in tm('advantages.items')" :key="index" class="relative pl-8">
                          <span class="absolute left-0 top-1.5 w-2 h-2 rounded-full border border-gold-500"></span>
                          
                          <h4 class="text-base font-bold text-gray-200 mb-1 group-hover:text-white transition-colors">{{ item.title }}</h4>
                          <p class="text-sm text-gray-400 group-hover:text-gray-300 transition-colors">{{ item.desc }}</p>
                      </li>
                  </ul>
              </div>
           </div>

           <!-- Column 3: Special Conditions -->
           <div class="group" v-motion-slide-visible-once-bottom :delay="200">
              <div class="relative h-full p-8 rounded-3xl bg-gradient-to-b from-gold-500/10 to-dark-800/50 backdrop-blur-xl border border-gold-500/30 shadow-[0_0_20px_rgba(212,175,55,0.1)] hover:border-gold-500/60 hover:shadow-[0_0_50px_rgba(212,175,55,0.25)] transition-all duration-500 hover:-translate-y-2">
                  <!-- Header -->
                  <div class="mb-8 relative">
                      <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-gold-400 to-gold-600 flex items-center justify-center mb-6 shadow-lg shadow-gold-500/20 group-hover:scale-110 transition-transform duration-500">
                          <UserGroupIcon class="w-6 h-6 text-dark-900" />
                      </div>
                      <h3 class="text-2xl font-kanit font-bold text-white uppercase tracking-wide group-hover:text-gold-300 transition-colors">
                          {{ t('conditions.title') }}
                      </h3>
                      <div class="absolute -bottom-4 left-0 w-12 h-1 bg-gold-500 rounded-full group-hover:w-full transition-all duration-700 ease-out"></div>
                  </div>

                  <!-- List -->
                  <ul class="space-y-6">
                      <li v-for="(item, index) in tm('conditions.items')" :key="index" class="relative pl-8">
                          <!-- Gold Bullet -->
                          <span class="absolute left-0 top-1.5 w-2 h-2 rounded-full bg-gold-500 shadow-[0_0_8px_rgba(212,175,55,0.8)]"></span>
                          
                          <h4 class="text-base font-bold text-gray-200 mb-1 group-hover:text-white transition-colors">{{ item.title }}</h4>
                          <p class="text-sm text-gray-400 group-hover:text-gray-300 transition-colors">{{ item.desc }}</p>
                      </li>
                  </ul>
              </div>
           </div>

        </div>
      </div>
    </section>
    



  </div>
</template>

<style scoped>
.perspective-1000 {
  perspective: 1000px;
}
.gold-bar-mockup {
  background: linear-gradient(135deg, #F5EECB 0%, #D4AF37 50%, #91751A 100%);
  transform: rotateX(10deg) rotateY(-10deg) rotateZ(5deg);
  box-shadow: 
    -5px 5px 20px rgba(0,0,0,0.5),
    inset 2px 2px 10px rgba(255,255,255,0.5),
    inset -2px -2px 10px rgba(0,0,0,0.2);
  transition: transform 0.5s ease;
}
.gold-bar-mockup:hover {
   transform: rotateX(5deg) rotateY(-5deg) rotateZ(2deg) scale(1.05);
}



@keyframes text-scan {
  0%, 100% { color: rgba(255, 255, 255, 1); text-shadow: none; opacity: 0.5; }
  10%, 90% { color: rgba(255, 255, 255, 1); text-shadow: none; opacity: 0.5; }
  50% { color: #EAB308; text-shadow: 0 0 20px rgba(234, 179, 8, 0.8); opacity: 1; }
}

@keyframes global-arrow {
  0% { top: -30%; opacity: 0; }
  5% { opacity: 1; }
  90% { opacity: 1; }
  100% { top: 130%; opacity: 0; }
}

.animate-global-arrow {
  animation: global-arrow 3s linear infinite;
}

@keyframes scan-dot {
  0%, 100% { background-color: #111827; border-color: rgba(255,255,255,0.1); box-shadow: none; }
  50% { background-color: #EAB308; border-color: #EAB308; box-shadow: 0 0 10px #EAB308; }
}

.animate-text-scan {
  animation: text-scan 3s ease-in-out infinite;
}

.animate-scan-line {
  animation: scan-line 3s ease-in-out infinite;
}

.animate-scan-dot {
  animation: scan-dot 3s ease-in-out infinite;
}

@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-4px); }
}

.animate-float-slow {
  animation: float 4s ease-in-out infinite;
}
</style>
