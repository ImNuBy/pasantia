
// Carousel simple
document.addEventListener('DOMContentLoaded', function(){
  const track = document.querySelector('.carousel-track');
  const slides = Array.from(track.children);
  const prev = document.querySelector('.prev');
  const next = document.querySelector('.next');
  let index = 0;
  const move = (i)=> {
    index = (i + slides.length) % slides.length;
    track.style.transform = `translateX(-${index * 100}%)`;
  };
  prev.addEventListener('click', ()=> move(index-1));
  next.addEventListener('click', ()=> move(index+1));
  // autoplay
  let autoplay = setInterval(()=> move(index+1), 4000);
  [prev,next,track].forEach(el=> el.addEventListener('mouseenter', ()=> clearInterval(autoplay)));
  [prev,next,track].forEach(el=> el.addEventListener('mouseleave', ()=> autoplay = setInterval(()=> move(index+1), 4000)));

  // modal image view
  document.querySelectorAll('.block img, .carousel-slide img').forEach(img=>{
    img.style.cursor = 'pointer';
    img.addEventListener('click', ()=>{
      const modal = document.querySelector('.modal');
      modal.querySelector('img').src = img.src;
      modal.classList.add('show');
    });
  });
  document.querySelector('.modal').addEventListener('click', (e)=>{
    if(e.target.classList.contains('modal')) e.currentTarget.classList.remove('show');
  });
});
