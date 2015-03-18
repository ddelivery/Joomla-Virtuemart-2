var topWindow = parent;

while(topWindow != topWindow.parent) {
    topWindow = topWindow.parent;
}
jQuery(function($){

    if(typeof(topWindow.DDeliveryIntegration) == 'undefined')
        topWindow.DDeliveryIntegration = (function(){
            var th = {};
            var status = 'Выберите условия доставки';
            var buttons = '#button-shipping-method,#simplecheckout_button_confirm,a#confirm,button#confirm,#button-confirm,#simplecheckout_next,#confirmbtn_button,button#confirmbtn' ;
            var button = null;
            
            th.getStatus = function(){
                return status;
            };
    
            function hideCover() {
                document.body.removeChild(document.getElementById('ddelivery_cover'));
            }
    
            function showPrompt() {
                var cover = document.createElement('div');
                cover.id = 'ddelivery_cover';
                document.body.appendChild(cover);
                document.getElementById('ddelivery_container').style.display = 'block';
            }
            
            function getFakeButton(){
                if ($('#fakeBtn').length > 0){
                    $('#fakeBtn').remove();
                }
                if ($('#fakeBtn').length == 0){
                    var text = '';
                    $(buttons).each(function(idx){
                       if ($(this).is(':visible')){
                           if ($(this).val())
                            text = $(this).val(); 
                           else 
                            text = $(this).text();
                           button = $(this); 
                       }
                    });
                    var clone = button.clone();
                    $(clone).attr('id','fakeBtn');
                    $(clone).attr('onclick','');
                    button.after(clone);
                    clone.click(function(){
                        //alert('Сначала выберите точку доставки DDelivery');
                        DDeliveryIntegration.openPopup();
                        return false;
                    });
                }
                return $('#fakeBtn');
                
            }
            
            th.showFakeButton = function showFakeButton(show){
                var fake_btn = getFakeButton();
                
                if (fake_btn == null && typeof fake_btn.css == 'undefined')
                    return;
                
                if (show == true){
                    $(button).css('display','none');
                    $(fake_btn).css('display','inline-block');
                }
                else{
                    $(button).css('display','inline-block');
                    $(fake_btn).css('display','none'); 
                    //alert((fake_btn.text())?fake_btn.text():fake_btn.val());   
                }
            }
    
            th.openPopup = function(){
                showPrompt();
                document.getElementById('ddelivery_popup').innerHTML = '';
                var params = {
                    formData: {}
                };
    
                var callback = {
                    close: function(){
                        hideCover();
                        document.getElementById('ddelivery_container').style.display = 'none';
                        this.updatePage();
                        if ($('label#dd_info').text()=='') th.showFakeButton(true);
                        else th.showFakeButton(false);
                    },
                    change: function(data) {
                        status = data.comment;
                        console.log(data);
                        hideCover();
                        document.getElementById('ddelivery_container').style.display = 'none';
                        document.getElementById('dd_info').innerHTML = data.comment;
                        document.getElementById('dd_price').innerHTML = data.clientPrice.toFixed(2) + ' руб.';
                        if (typeof dd_shipment_id !== 'undefined')
                                if (document.getElementById('shipment_id_' + dd_shipment_id) !== null){
                                document.getElementById('shipment_id_' + dd_shipment_id).click();
                                }
                        
                        if (typeof Onepage !== 'undefined'){
                            
                            $('input[name=virtuemart_paymentmethod_id]').each(function(idx){
                                var base = '#shipment_id_'+dd_shipment_id+'_' +$(this).val();
                                $(base+'_order_shipping').val(data.clientPrice.toFixed(2));
                                var subtotal = parseFloat($(base+'_subtotal').val());
                                //var tax_all = parseFloat($(base+'_tax_all').val());
                                var order_shipping = parseFloat($(base+'_order_shipping').val());
                                var payment_discount = parseFloat($(base+'_payment_discount').val());
                                var coupon_discount = parseFloat($(base+'_coupon_discount').val());
                                var coupon_discount2 = parseFloat($(base+'_coupon_discount2').val());
                                var order_total = subtotal + order_shipping + payment_discount - coupon_discount - coupon_discount2; 
                                $(base+'_order_total').val(order_total.toFixed(2));
                                //alert("subtotal: "+subtotal);
                                //alert("tax_all: "+tax_all);
                                //alert("order_shipping: "+order_shipping);
                                //alert("payment_discount: "+payment_discount);
                                //alert("coupon_discount: "+coupon_discount);
                                //alert(order_total);
                                    
                            });
                            $('label[for=shipment_id_'+dd_shipment_id+']').find('.vmshipment_cost').text('(Стоимость : '+ data.clientPrice.toFixed(2) +' руб)');
                            Onepage.changeTextOnePage3(op_textinclship, op_currency, op_ordertotal);
                            //Onepage.resetShipping(); 
                            //Onepage.showcheckout();
                        }
                        if (typeof checkoutForm !== 'undefined' && typeof checkoutForm.setshipment !== 'undefined')
                            checkoutForm.setshipment.click();
                        else if (typeof chooseShipmentRate !== 'undefined' && typeof chooseShipmentRate.setshipment !== 'undefined') 
                            chooseShipmentRate.setshipment.click();
                        //else{
                        //    location.reload(true);
                        //}
                        
                        $('#ddelivery_container').css('display','none');
                        $('label#dd_info').text(data.comment);
                        jQuery('label[for^=ddelivery]:eq(1)').text(data.clientPrice.toFixed(2) + ' руб.');
                        jQuery('#button-shipping-method').css('display','inline-block');
                        this.updatePage();
                        th.showFakeButton(false);
                    },
                    updatePage: function(){
                        if (typeof overlay_simplecheckout !== 'undefined')
                            simplecheckout_reload('shipping_changed');
                        else if (typeof window.simplecheckout !== 'undefined' && typeof window.simplecheckout.reloadAll !== 'undefined')
                            window.simplecheckout.reloadAll();
                    }
                };
                
                callback.updatePage();
                console.log($.param($('#dd_info').parents('form').serializeArray()));
                DDelivery.delivery('ddelivery_popup', '/plugins/vmshipment/ddelivery/ddelivery/ajax.php?'+$.param($('#dd_info').parents('form').serializeArray()), {orderId: 4,dd_plugin:1}, callback);
                return void(0);
            };
            
            ready(function (){
            var body = document.getElementsByTagName('body')[0];
                
                var style = document.createElement('STYLE');
                style.innerHTML = // Скрываем ненужную кнопку
                    " #delivery_info_ddelivery_all a{display: none;} " +
                    " #ddelivery_popup { display: inline-block; vertical-align: middle; margin: 10px auto; width: 1000px; height: 650px;} " +
                    " #ddelivery_container { position: fixed; top: 0; left: 0; z-index: 9999;display: none; width: 100%; height: 100%; text-align: center;  } " +
                    " #ddelivery_container:before { display: inline-block; height: 100%; content: ''; vertical-align: middle;} " +
                    " #ddelivery_cover {  position: fixed; top: 0; left: 0; z-index: 9000; width: 100%; height: 100%; background-color: #000; background: rgba(0, 0, 0, 0.5); filter: progid:DXImageTransform.Microsoft.gradient(startColorstr = #7F000000, endColorstr = #7F000000); } ";
                body.appendChild(style);
                
                var div = document.createElement('div');
                div.innerHTML = '<div id="ddelivery_popup"></div>';
                div.id = 'ddelivery_container';
                body.appendChild(div);
                
                //}
            });
            return th;
        })();
    
    var DDeliveryIntegration = topWindow.DDeliveryIntegration;
    if (document.getElementById('select_way') == null){
        $('label[for^=ddelivery]:eq(0)').after('<a href="javascript:void(null)" onclick="DDeliveryIntegration.openPopup();" id="select_way" class="trigger">Выбрать точку доставки</a>');
    }
        
    $('input:radio[name=virtuemart_shipmentmethod_id]').click(function (){
        if ($(this).val() == 'ddelivery.ddelivery'){
            DDeliveryIntegration.openPopup();
            if ($('label#dd_info').text()==''){
                DDeliveryIntegration.showFakeButton(true);
                }
        }
        else{
            DDeliveryIntegration.showFakeButton(false);
        }
        
    });
    
    $(document).ready(function(){
        
        //alert($('#shipment_id_' + dd_shipment_id).val());
        //alert(dd_shipment_id);
        setInterval(function(){
            
            //alert($('#dd_info').text());
            if ($('input:radio[name=virtuemart_shipmentmethod_id]:checked').val() == dd_shipment_id && $('#dd_info').text()=='' && 
                typeof $('#select_way') !== null && $('#select_way').is(':visible')){
                DDeliveryIntegration.showFakeButton(true);
            }
            else{
                //alert('hide');
                DDeliveryIntegration.showFakeButton(false);
                
            }    
        },1000);
    
    });


});

/* Хуки на выбор компании или точки
mapPointChange: function(data) {},
courierChange: function(data) {}
*/