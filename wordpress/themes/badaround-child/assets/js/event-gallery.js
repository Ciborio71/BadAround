(() => {
    'use strict';
    document.querySelectorAll('[data-ba-event-gallery]').forEach((gallery) => {
        const image=gallery.querySelector('[data-ba-gallery-main]'), full=gallery.querySelector('[data-ba-gallery-full]');
        const thumbs=Array.from(gallery.querySelectorAll('[data-ba-gallery-thumb]'));
        const counter=gallery.querySelector('[data-ba-gallery-count]'),prev=gallery.querySelector('[data-ba-gallery-prev]'),next=gallery.querySelector('[data-ba-gallery-next]');
        if(!image||!full||thumbs.length<2)return;
        let current=0, pointer=null,swiped=false;
        function show(index){
            current=(index+thumbs.length)%thumbs.length;const thumb=thumbs[current];
            // Drop the previous responsive source set before selecting the new public image.
            image.removeAttribute('srcset');image.width=Number(thumb.dataset.width);image.height=Number(thumb.dataset.height);
            image.alt=thumb.dataset.alt;image.src=thumb.dataset.src;
            if(thumb.dataset.srcset)image.setAttribute('srcset',thumb.dataset.srcset);
            full.href=thumb.href;thumbs.forEach((t,i)=>i===current?t.setAttribute('aria-current','true'):t.removeAttribute('aria-current'));
            counter.textContent=`Foto ${current+1} di ${thumbs.length} · Apri la foto per ingrandirla`;
        }
        thumbs.forEach((thumb,i)=>thumb.addEventListener('click',(e)=>{e.preventDefault();show(i);}));
        prev.hidden=false;next.hidden=false;prev.addEventListener('click',()=>show(current-1));next.addEventListener('click',()=>show(current+1));
        gallery.addEventListener('keydown',(e)=>{
            let index;
            if(e.key==='ArrowRight')index=current+1;else if(e.key==='ArrowLeft')index=current-1;else if(e.key==='Home')index=0;else if(e.key==='End')index=thumbs.length-1;else return;
            e.preventDefault();show(index);thumbs[current].focus();
        });
        const stage=gallery.querySelector('[data-ba-gallery-stage]');
        stage.addEventListener('pointerdown',(e)=>{if(e.pointerType==='touch'){pointer={x:e.clientX,y:e.clientY};swiped=false;}},{passive:true});
        stage.addEventListener('pointercancel',()=>{pointer=null;});
        stage.addEventListener('pointerup',(e)=>{if(!pointer)return;const dx=e.clientX-pointer.x,dy=e.clientY-pointer.y;pointer=null;if(Math.abs(dx)>50&&Math.abs(dx)>Math.abs(dy)*1.2){swiped=true;show(current+(dx<0?1:-1));}},{passive:true});
        full.addEventListener('click',(e)=>{if(swiped){e.preventDefault();swiped=false;}});
        image.addEventListener('error',()=>{counter.textContent='Foto non disponibile. Seleziona un’altra miniatura.';});
    });
})();
