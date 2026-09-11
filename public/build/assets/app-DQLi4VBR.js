var e=document.getElementById(`pm-data`),t=e?JSON.parse(e.dataset.findings||`{}`):{},n=e?JSON.parse(e.dataset.spareparts||`[]`):[],r=e?JSON.parse(e.dataset.bigProblems||`[]`):[],i=e?Number(e.dataset.problemIndex||0):0,a=e?Number(e.dataset.sparepartIndex||0):0,o=!1;function s(e){return String(e??``).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`).replace(/'/g,`&#39;`)}function c(e){return`
        <div class="problem-row mb-2 flex gap-2 max-sm:mb-3 max-sm:flex-col max-sm:rounded-xl max-sm:border max-sm:border-gray-200 max-sm:bg-gray-50 max-sm:p-3">
            <select name="problems[${e}][problem]" class="problem-select w-1/2 rounded-xl border border-gray-300 p-2 text-sm max-sm:w-full">
                <option value="">-- Select Problem --</option>
                ${r.map(e=>{let t=s((e.category||``).trim().toLowerCase()),n=s(e.problem||``);return`<option value="${s(e.id)}" data-category="${t}">${n}</option>`}).join(``)}
            </select>

            <select name="problems[${e}][finding]" class="finding-select flex-1 rounded-xl border border-gray-300 p-2 text-sm max-sm:w-full">
                <option value="">-- Finding --</option>
            </select>

            <select name="problems[${e}][severity]" class="w-1/5 rounded-xl border border-gray-300 p-2 text-sm max-sm:w-full">
                <option value="">-- Severity --</option>
                <option value="Low">Low</option>
                <option value="Medium">Medium</option>
                <option value="High">High</option>
            </select>

            <button type="button" onclick="removeProblem(this)" class="rounded-xl bg-red-500 px-3 py-2 text-white transition hover:bg-red-600 max-sm:w-full max-sm:py-2">
                X
            </button>
        </div>
    `}function l(e){return`
        <div class="sparepart-row mb-2 flex gap-2 max-sm:mb-3 max-sm:flex-col max-sm:rounded-xl max-sm:border max-sm:border-gray-200 max-sm:bg-gray-50 max-sm:p-3">
            <select name="spareparts[${e}][sparepart_id]" placeholder="Select Sparepart" class="sparepart-select w-2/3 rounded-xl border border-gray-300 p-2 text-sm max-sm:w-full"></select>

            <input type="number" name="spareparts[${e}][qty]" placeholder="Qty" class="w-1/3 rounded-xl border border-gray-300 p-3 text-sm max-sm:w-full">

            <button type="button" onclick="removeSparepart(this)" class="rounded-xl bg-red-500 px-3 py-2 text-white transition hover:bg-red-600 max-sm:w-full max-sm:py-2">
                X
            </button>
        </div>
    `}window.addProblem=function(){let e=document.getElementById(`problem-wrapper`);e&&(e.insertAdjacentHTML(`beforeend`,c(i)),i++)},window.removeProblem=function(e){document.querySelectorAll(`.problem-row`).length>1&&e?.parentElement&&e.parentElement.remove()};function u(e){let n=e.selectedOptions[0];if(!n||!n.dataset.category)return;let r=n.dataset.category.trim().toLowerCase(),i=e.closest(`.problem-row`)?.querySelector(`.finding-select`);if(!i)return;let a=i.dataset.oldFinding;i.innerHTML=`<option value="">-- Finding --</option>`,t[r]&&t[r].forEach(function(e){let t=e.id==a?`selected`:``;i.innerHTML+=`
                <option value="${e.id}" ${t}>
                    ${e.finding}
                </option>
            `})}function d(){document.querySelectorAll(`.problem-select`).forEach(function(e){e.value&&u(e)})}function f(){let e=document.getElementById(`start_time`),t=document.getElementById(`end_time`),n=document.getElementById(`duration`),r=document.getElementById(`duration_error`);if(!e||!t||!n||!r||!e.value||!t.value)return;let i=e.value.split(`:`),a=t.value.split(`:`),o=parseInt(i[0],10)*60+parseInt(i[1],10),s=parseInt(a[0],10)*60+parseInt(a[1],10);if(s<o){n.value=``,r.classList.remove(`hidden`),t.classList.add(`border-red-500`);return}r.classList.add(`hidden`),t.classList.remove(`border-red-500`);let c=s-o;n.value=`${Math.floor(c/60)} Hours ${c%60} Minutes`}function p(e){window.TomSelect&&(e.tomselect&&e.tomselect.destroy(),new window.TomSelect(e,{valueField:`id`,labelField:`material_number`,searchField:[`location`,`material_number`,`description`,`remarks`],options:n,items:e.dataset.selected?[e.dataset.selected]:[],create:!1,maxOptions:100,render:{option:function(e,t){return`
                    <div style="padding:8px">
                        <div style="font-size:12px;color:#6b7280">
                            📍 ${t(e.location??`-`)}
                        </div>
                        <div style="font-weight:700">
                            ${t(e.material_number)}
                        </div>
                        <div>
                            ${t(e.description)}
                        </div>
                        <div style="font-size:12px;color:#6b7280">
                            ${t(e.remarks??`-`)}
                        </div>
                    </div>
                `},item:function(e,t){return`
                    <div>
                        ${t(e.material_number)}
                        -
                        ${t(e.description)}
                    </div>
                `}}}))}function m(){document.querySelectorAll(`.sparepart-select`).forEach(function(e){p(e)})}window.addSparepart=function(){let e=document.getElementById(`sparepart-wrapper`);if(!e)return;e.insertAdjacentHTML(`beforeend`,l(a));let t=e.querySelectorAll(`.sparepart-select`);t.length&&p(t[t.length-1]),a++},window.removeSparepart=function(e){e?.closest(`.sparepart-row`)?.remove()};function h(){return window.TomSelect?Promise.resolve():new Promise((e,t)=>{let n=document.createElement(`script`);n.src=`https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js`,n.onload=e,n.onerror=t,document.head.appendChild(n)})}function g(){if(o)return;o=!0,document.addEventListener(`change`,function(e){if(e.target.classList.contains(`problem-select`)&&u(e.target),e.target.classList.contains(`session-start`)||e.target.classList.contains(`session-end`)){let t=e.target.closest(`.session-row`);t&&(v(t),y())}}),d();let e=document.getElementById(`start_time`),t=document.getElementById(`end_time`);e?.addEventListener(`change`,f),t?.addEventListener(`change`,f),m(),document.getElementById(`add-session`)?.addEventListener(`click`,b),document.querySelectorAll(`.remove-session`).forEach(function(e){e.addEventListener(`click`,function(){e.closest(`.session-row`)?.remove(),y()})}),document.querySelectorAll(`.session-row`).forEach(function(e){v(e)}),y()}function _(e){if(!e)return null;let t=e.split(`:`);return t.length===2?parseInt(t[0],10)*60+parseInt(t[1],10):null}function v(e){let t=e.querySelector(`.session-start`)?.value||null,n=e.querySelector(`.session-end`)?.value||null,r=e.querySelector(`.session-duration`);if(!t||!n){r&&(r.textContent=`-`),e.dataset.duration=``;return}let i=_(t),a=_(n);if(i===null||a===null){r&&(r.textContent=`-`),e.dataset.duration=``;return}let o=a-i;o<0&&(o+=1440);let s=Math.floor(o/60),c=o%60;r&&(r.textContent=`${s} Hours ${c} Minutes`),e.dataset.duration=o.toString()}function y(){let e=document.querySelectorAll(`.session-row`),t=0;e.forEach(function(e){let n=parseInt(e.dataset.duration||`0`,10);isNaN(n)||(t+=n)});let n=document.getElementById(`total-duration`);if(n){let e=Math.floor(t/60),r=t%60;t===0?n.textContent=n.dataset.initial||``:n.textContent=`${e} Hours ${r} Minutes`}}function b(){let e=document.getElementById(`work-sessions`);if(!e)return;let t=e.querySelectorAll(`.session-row`).length,n=`
        <div class="session-row rounded-lg border p-3" data-index="${t}">
            <input type="hidden" name="sessions[${t}][id]" value="">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="text-xs">Date</label>
                    <input type="date" name="sessions[${t}][actual_date]" value="" class="w-full border rounded-2xl p-2">
                </div>
                <div>
                    <label class="text-xs">Start</label>
                    <input type="time" name="sessions[${t}][start_time]" value="" class="w-full border rounded-2xl p-2 session-start">
                </div>
                <div>
                    <label class="text-xs">End</label>
                    <input type="time" name="sessions[${t}][end_time]" value="" class="w-full border rounded-2xl p-2 session-end">
                </div>
            </div>
            <div class="mt-2 flex items-center justify-between">
                <div class="text-sm text-slate-600">Duration: <span class="session-duration">-</span></div>
                <div>
                    <button type="button" class="remove-session inline-flex items-center rounded-lg bg-red-500 px-3 py-1 text-white">Remove Day</button>
                </div>
            </div>
        </div>
    `;e.insertAdjacentHTML(`beforeend`,n);let r=e.querySelector(`.session-row[data-index="`+t+`"]`);r&&(r.querySelector(`.remove-session`).addEventListener(`click`,function(){r.remove(),y()}),r.querySelector(`.session-start`)?.addEventListener(`change`,function(){v(r),y()}),r.querySelector(`.session-end`)?.addEventListener(`change`,function(){v(r),y()}))}document.readyState===`loading`?document.addEventListener(`DOMContentLoaded`,function(){h().then(g).catch(()=>{g()})}):h().then(g).catch(()=>{g()});var x=null;function S(){let e=document.getElementById(`remarkModal`),t=document.getElementById(`remarkTextarea`),n=document.getElementById(`remarkItem`);!e||!t||!n||(document.querySelectorAll(`.remark-btn`).forEach(r=>{r.addEventListener(`click`,function(){x=this.dataset.index,n.innerText=this.dataset.item,t.value=document.getElementById(`remark-`+x).value,e.classList.remove(`hidden`),e.classList.add(`flex`)})}),document.getElementById(`cancelRemark`)?.addEventListener(`click`,function(){e.classList.add(`hidden`),e.classList.remove(`flex`)}),document.getElementById(`saveRemark`)?.addEventListener(`click`,function(){let n=document.getElementById(`remark-`+x);if(!n)return;n.value=t.value;let r=document.querySelector(`.remark-btn[data-index="${x}"]`);t.value.trim()===``?(r?.classList.remove(`text-green-600`),r?.classList.add(`text-gray-500`),r.innerHTML=`✏️`):(r?.classList.remove(`text-gray-500`),r?.classList.add(`text-green-600`),r.innerHTML=`📝`),e.classList.add(`hidden`),e.classList.remove(`flex`)}),e.addEventListener(`click`,function(t){t.target===e&&(e.classList.add(`hidden`),e.classList.remove(`flex`))}))}document.readyState===`loading`?document.addEventListener(`DOMContentLoaded`,S):S();