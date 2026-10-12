'use strict';
let installPrompt;
const installButton=document.getElementById('install-app');
const help=document.getElementById('install-help');
const standalone=()=>matchMedia('(display-mode: standalone)').matches||navigator.standalone===true;
function visibility(){installButton.hidden=standalone();}
visibility();
window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();installPrompt=e;installButton.textContent='Install mobile app';visibility();});
window.addEventListener('appinstalled',()=>{installPrompt=null;visibility();});
installButton.addEventListener('click',async()=>{if(installPrompt){await installPrompt.prompt();await installPrompt.userChoice;installPrompt=null;}else help.showModal();});
help.querySelector('button').addEventListener('click',()=>help.close());
if('serviceWorker' in navigator&&window.isSecureContext)navigator.serviceWorker.register('sw.js').catch(()=>{installButton.title='Installation is temporarily unavailable. Reload and try again.';});
