(function(w,k,o) {
var t=setTimeout(f,{config_timeout});
k.forEach(function(e){w.addEventListener(e,f,o)});
function f(){
document.querySelectorAll("script[data-lazy='w3tc']").forEach(function(i){i.src = i.dataset.src})
clearTimeout(t);
k.forEach(function(e){w.removeEventListener(e,f,o)})
}
})(window,["keydown","mouseover","touchmove","touchstart","wheel"],{passive:!0})
