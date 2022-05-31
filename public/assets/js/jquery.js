/* jQuery (function() {
    var multipleCancelButton = new Choices('.multiple-emsFormBuilder', {
    maxItemCount:10,
    searchResultLimit:10,
    renderChoiceLimit:10,
    removeItemButton: true
    });
    }); */


    document.getElementById("choices-multiple-remove-button").addEventListener("change", (e) => {
        e.preventDefault();
        // اگر مولتی سلکت بود از طریق کد زیر مقدار ولیو انتخابی بدست می آید
        const el = document.getElementById(e.target.id);
        for (let i = 0; i < el.children.length; i++) {
       
        }
      });

 