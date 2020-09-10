var clickEvent = ($.browser.mobile) ? "touchstart" : "click";
$(function(){
	auction.init();
	auction.console.init();
});
var auction={
	firstRun:false,
	body:null,
	tpl:false,
	bid:false,
	myBidInexes:{},
	ws:false,
	top:0,
	init:function(){
		var obj=this;
		$('body').addClass('loading');
		obj.tab.init();
		obj.auctionHash.init();
		if(obj.body===null){
			var headers={
				'Content-Type':'application/json'
			};
			if(obj.console.getSign()) headers['X-Sign']=obj.console.getSign();
			if(obj.tab.getID()) headers['X-Tab']=obj.tab.getID();
			$.ajax({
				type: 'GET',
				url: window.location.pathname+'/index.json',
				dataType: "json",
				cache: false,
				headers:headers,
				complete: function(jqXHR, textStatus,request) {
					if(jqXHR.readyState==0 && jqXHR.statusText=='error' && jqXHR.status==0){
						obj.console.log('load json data',jqXHR.statusText);
						$('#connectionError').addClass('active');
						setTimeout(function(){obj.init();},1000);
					}
					if(jqXHR.readyState==4){
						obj.console.setSign(jqXHR.getResponseHeader('X-Sign'));
						obj.body=jqXHR;
						if(obj.body.responseJSON && obj.body.responseJSON.is_bidder) obj.console.log('load json data',window.location.toString());
						obj.init();
					}
				}
			});
			return;
		}
		if(!obj.tpl){
			$.ajax({
				type: 'GET',
				url: '/tpl/auction.htm?v=3',
				cache: true,
				complete: function(jqXHR, textStatus) {
					if(jqXHR.readyState==0 && jqXHR.statusText=='error' && jqXHR.status==0){
						obj.console.log('load auction.htm',jqXHR.statusText);
						$('#connectionError').addClass('active');
						//alert(jqXHR.statusText);
					}
					if(jqXHR.readyState==4){
						if(jqXHR.status==200){
							obj.tpl=$(jqXHR.responseText);
							//$('body').append(jqXHR.responseText);
							obj.init();
						}
					}
				}
			});
			return;
		}
		if(obj.body.status==404){
			alert('<div data-msg="auction no found" />');
			//return;
		}
		if(!obj.body.responseJSON || obj.body.responseJSON.isEnded) obj.body.responseJSON.is_bidder=false;
		if(!obj.bid && obj.body.responseJSON.is_bidder){
			$.ajax({
				type: 'GET',
				url: '/tpl/bid.htm?v=3',
				cache: true,
				complete: function(jqXHR, textStatus) {
					if(jqXHR.readyState==0 && jqXHR.statusText=='error' && jqXHR.status==0){
						obj.console.log('load bid.htm',jqXHR.statusText);
						$('#connectionError').addClass('active');
						//alert(jqXHR.statusText);
					}
					if(jqXHR.readyState==4){
						if(jqXHR.status==200){
							obj.bid=$(jqXHR.responseText);
							obj.init();
						}
					}
				}
			});
			return;
		}
		
		
		if(obj.body && obj.body.status==200){
			obj.body.responseJSON.startTime=parseInt(obj.body.responseJSON.startTime);
			obj.body.responseJSON.endTime=parseInt(obj.body.responseJSON.endTime);
			obj.body.responseJSON.currentTime=parseInt(obj.body.responseJSON.currentTime);
			obj.bidControlsCounter=0;
			
			function addZero(i) {
				if (i < 10) {
					i = "0" + i;
				}
				return i;
			}
			
			obj.body.responseJSON.startTimeString=new Date();
			obj.body.responseJSON.startTimeString.setTime(obj.body.responseJSON.startTime*1000);
			obj.body.responseJSON.startTimeString=obj.body.responseJSON.startTimeString.getDate()+' <span data-msg="month_'+obj.body.responseJSON.startTimeString.getMonth()+'" /> '+obj.body.responseJSON.startTimeString.getFullYear()+' '+addZero(obj.body.responseJSON.startTimeString.getHours())+':'+addZero(obj.body.responseJSON.startTimeString.getMinutes())+':'+addZero(obj.body.responseJSON.startTimeString.getSeconds());
			
			$('#body').append($('#auctionBody',obj.tpl).tmpl(obj.body.responseJSON));
			$('body').removeClass('loading');
			
			if(obj.body.responseJSON.currentTime<obj.body.responseJSON.startTime){
				var t=(obj.body.responseJSON.startTime-obj.body.responseJSON.currentTime)*1000
				setTimeout(function(){
					obj.reload();
				},t>2147483647?2147483647:t);
				$('body .page').addClass('auctionStatus_pending');
				obj.timerDown();
				obj.console.clear();
			}
			else {
				if(obj.wsCheckTime===null) obj.wsCheckTime=obj.body.responseJSON.is_bidder?10000:60000;
				
				var min=false;
				for(var i=0;i<obj.body.responseJSON.auctionsSteps[0].length;i++){
					if(min===false || parseFloat(obj.body.responseJSON.auctionsSteps[0][i].value)<obj.body.responseJSON.auctionsSteps[0][min].value) min=i;
					obj.body.responseJSON.auctionsSteps[0][i].stepIndex=0;
				}
				obj.body.responseJSON.auctionsSteps[0][min].minValue=true;
				var auctionsSteps=[obj.body.responseJSON.auctionsSteps[0]];
				/*var d=new Date;
				d.setTime(obj.body.responseJSON['startTime']*1000);
				console.log(d.toString());*/
				
				var finalStep=obj.body.responseJSON.source.settings.bidsSteps+1;
				
				for(var i=1;i<obj.body.responseJSON.source.settings.bidsSteps+2;i++){
					if(!obj.body.responseJSON.auctionsSteps[i]) obj.body.responseJSON.auctionsSteps[i]=[];
					var data={};
					for(var j=0;j<obj.body.responseJSON.auctionsSteps[i].length;j++){
						data[obj.body.responseJSON.auctionsSteps[i][j]['id']]=obj.body.responseJSON.auctionsSteps[i][j];
					}
					obj.body.responseJSON.auctionsSteps[i]=[];
					
					//var min=false;
					for(var j=0;j<obj.body.responseJSON.auctionsSteps[0].length;j++){
						var ne={};
						$.extend(true,ne,(data[obj.body.responseJSON.auctionsSteps[0][j]['id']]?data[obj.body.responseJSON.auctionsSteps[0][j]['id']]:obj.body.responseJSON.auctionsSteps[i-1][j]));
						delete ne['minValue'];
						if(i==finalStep){
							ne.discount=(1-ne.value/obj.body.responseJSON.auctionsSteps[0][j].value)*100;
							/*if( min===false 
								|| parseFloat(ne.value)<obj.body.responseJSON.auctionsSteps[i][min].value
								|| (parseFloat(ne.value)==obj.body.responseJSON.auctionsSteps[i][min].value && obj.body.responseJSON.auctionsSteps[0][min].pendingTime && obj.body.responseJSON.auctionsSteps[0][j].pendingTime && obj.body.responseJSON.auctionsSteps[0][min].pendingTime>obj.body.responseJSON.auctionsSteps[0][j].pendingTime)
							) min=j;*/
						}
						ne.stepIndex=i;
						ne.bidderIndex=j+1;
						ne.beforeValue=obj.body.responseJSON.auctionsSteps[i-1][j].value;
						ne.pendingTime=obj.body.responseJSON.auctionsSteps[0][j].pendingTime?obj.body.responseJSON.auctionsSteps[0][j].pendingTime:0;
						if(obj.body.responseJSON.is_bidder){
							if(obj.body.responseJSON.lastBid[0]['__index__']==j) {
								ne.my_bid=1;
							}
						}
						obj.body.responseJSON.auctionsSteps[i].push(ne);
					}
				}
				for(var i=1;i<obj.body.responseJSON.source.settings.bidsSteps+2;i++){
					obj.body.responseJSON.auctionsSteps[i].sort(function (vote1, vote2) {
						if (parseFloat(vote1.beforeValue) < parseFloat(vote2.beforeValue)) return 1;
						if (parseFloat(vote1.beforeValue) > parseFloat(vote2.beforeValue)) return -1;

						if (parseInt(vote1.pendingTime) < parseInt(vote2.pendingTime)) return 1;
						if (parseInt(vote1.pendingTime) > parseInt(vote2.pendingTime)) return -1;
					});
					
					var min=false;
					for(var j=0;j<obj.body.responseJSON.auctionsSteps[i].length;j++){
						var ne=obj.body.responseJSON.auctionsSteps[i][j];
						if(i==finalStep){
							if( min===false 
								|| parseFloat(ne.value)<obj.body.responseJSON.auctionsSteps[i][min].value
								|| (parseFloat(ne.value)==obj.body.responseJSON.auctionsSteps[i][min].value && obj.body.responseJSON.auctionsSteps[0][min].pendingTime && obj.body.responseJSON.auctionsSteps[0][j].pendingTime && obj.body.responseJSON.auctionsSteps[0][min].pendingTime>obj.body.responseJSON.auctionsSteps[0][j].pendingTime)
							) min=j;
						}
						if(ne.my_bid) obj.myBidInexes[i]=j;
						if(i && i<finalStep){
							ne.startTime=obj.body.responseJSON['startTime']+(obj.body.responseJSON['source']['settings']['bidStepTime']+obj.body.responseJSON['source']['settings']['bidStepPause'])*((i-1)*auctionsSteps[0].length+j)+obj.body.responseJSON['source']['settings']['bidStepPause'];
							ne.endTime=ne.startTime+obj.body.responseJSON['source']['settings']['bidStepTime'];
							ne.endPause=ne.endTime+obj.body.responseJSON['source']['settings']['bidStepPause'];
							
							ne.bidstep=obj.setTimerCount(false,obj.body.responseJSON['source']['settings']['bidStepTime']);
							ne.discount=(1-ne.value/obj.body.responseJSON.auctionsSteps[0][ne.bidderIndex-1].value)*100;
						}
					}
					
					if(i==finalStep) obj.body.responseJSON.auctionsSteps[i][min].minValue=true;
					auctionsSteps.push(obj.body.responseJSON.auctionsSteps[i]);
				}
				obj.body.responseJSON.auctionsSteps=auctionsSteps;
				
				for(var i=0;i<obj.body.responseJSON.auctionsSteps.length;i++){
					var data={
						index:i.toString(),
						bids:obj.body.responseJSON.auctionsSteps[i]
					};					
					$('#auctionsSteps').append($('#auctionsStepsTpl',obj.tpl).tmpl(data));
					$('#auctionsSteps .pauseTime').hide();
				}
				if(obj.body.responseJSON.currentTime<obj.body.responseJSON.endTime){
					if(obj.body.responseJSON.currentTime<obj.body.responseJSON.endTime-obj.body.responseJSON['source']['settings']['bidStepPause']) obj.wsConnect();
					var stepTime=(obj.body.responseJSON['source']['settings']['bidStepTime']+obj.body.responseJSON['source']['settings']['bidStepPause'])*obj.body.responseJSON['auctionsSteps'][0].length,
						step=Math.floor((obj.body.responseJSON.currentTime-obj.body.responseJSON['startTime'])/stepTime);
					
					if(step<0) {}
					else {
						for(var i=0;i<obj.body.responseJSON.auctionsSteps[0].length;i++){
							var start=obj.body.responseJSON['startTime']+step*stepTime+(i+1)*(obj.body.responseJSON['source']['settings']['bidStepPause']+obj.body.responseJSON['source']['settings']['bidStepTime']);
							if(start>obj.body.responseJSON.currentTime && (!obj.body.responseJSON.lastBid || i!=obj.myBidInexes[step+1]/*i!=obj.body.responseJSON.lastBid[0]['__index__']*/)
							&& (!obj.nextReload || (start>obj.nextReload && obj.nextReload>obj.body.responseJSON.currentTime))){
								obj.nextReload=start+1;
								break;
							}
						}
					}
					
					obj.sinhroTime();
					
					//if(obj.body.responseJSON.is_bidder){
						obj.bidControls();
					//}
					$('body .page').addClass('auctionStatus_live');
					if(obj.wsBidders) obj.setBiddersOnline(obj.wsBidders);
				}
				else {
					if(obj.sinhroTimeIns){
						clearInterval(obj.sinhroTimeIns);
						clearTimeout(obj.sinhroTimeIns);
					}
					if(obj.ws) {//console.log(ws.close());
						obj.ws.close();
						obj.ws=false;
					}
					$('#connectionError').removeClass('active');
					
					$('body .page').addClass('auctionStatus_complete');
					
					if(obj.console.haveLogs()) obj.console.log('auction status','complete');
				}
				obj.switchRoundStatus();
			}
			
			if(obj.body.responseJSON.is_bidder) obj.bidInit();
			else $('#bidding').remove();
		}
		else {
			$('#body').append($('#auctionError',obj.tpl).tmpl({
				status:obj.body.status
			}));
			$('body').removeClass('loading');
		}
		obj.initLang();
		$(window).scrollTop(obj.top);
		if(obj.body.status==200 && !obj.firstRun && obj.body.responseJSON.currentTime>=obj.body.responseJSON.startTime){
			obj.firstRun=true;
			if(obj.body.responseJSON.is_bidder){
				alert('<div data-msg="auction enter bidder" />');
				$('body').attr('data-is-bidder','true');
			}
			else if(obj.body.responseJSON.currentTime && obj.body.responseJSON.currentTime<obj.body.responseJSON.endTime){
				var query=this.parseQuery();
				
				if(query.bid_id) alert('<div data-msg="auction enter wrong bidder" />');
				else alert('<div data-msg="auction enter guest" />');
				
				$('body').attr('data-is-bidder','false');
			}
		}
	},
	reload:function(){
		this.top=$(window).scrollTop();
		$('#body').html('');
		this.body=null;
		this.nextReload=null;
		this.init();
	},
	sinhroTimeIns:null,
	sinhroTime:function(){
		var obj=this;
		if(obj.sinhroTimeIns){
			clearInterval(obj.sinhroTimeIns);
			clearTimeout(obj.sinhroTimeIns);
		}
		obj.sinhroTimeIns=setTimeout(function(){
			$.ajax({
				type: 'GET',
				url: '/auctions/sinhroTime',
				dataType: "json",
				cache: false,
				headers:{
					'Content-Type':'application/json'
				},
				complete: function(jqXHR, textStatus) {
					if(jqXHR.readyState==0 && jqXHR.statusText=='error' && jqXHR.status==0){
						obj.console.log('load sinhroTime',jqXHR.statusText);
						$('#connectionError').addClass('active');
						//alert(jqXHR.statusText);
					}
					if(jqXHR.readyState==4){
						if(jqXHR.status==200 && jqXHR.responseJSON && obj.body){
							if(jqXHR.responseJSON.currentTime && obj.body && obj.body.responseJSON){
								obj.body.responseJSON.currentTime=parseInt(jqXHR.responseJSON.currentTime);
								obj.bidControlsCounter=0;
							}							
						}
						obj.sinhroTime();
					}
				}
			});
		},60000);
	},
	wsCheckTime:null,
	wsBidders:null,
	wsConnect:function(){
		var obj=this;
		if(!obj.body || !obj.body.responseJSON || !obj.body.responseJSON.is_bidder || obj.body.responseJSON.isEnded || obj.body.responseJSON.currentTime>=obj.body.responseJSON.endTime) {
			if(obj.ws.reinit){
				clearInterval(obj.ws.reinit);
				clearTimeout(obj.ws.reinit);				
			}
			return false;
		}
		if(!obj.ws && window['WebSocket']){
			obj.console.log('no ws');
			var url='';
			switch(window.location.protocol){
				case 'https:':
					url="wss://"+window.location.host+':'+(window.location.port?window.location.port:443);
					break;
				default:
					url="ws://"+window.location.host+':'+(window.location.port?window.location.port:80);
			}
			url+="/ws"+window.location.pathname+(window.location.search?window.location.search:'');
			if(obj.tab.getID()) url+=(window.location.search?'&':'?')+'X-Tab='+obj.tab.getID();
			
			obj.ws = new WebSocket(url);
			
			obj.WSsessionID=obj.tab.genID();
			obj.ws.sessionID=obj.WSsessionID;
			
			obj.ws.reinit=null;
			obj.ws.checking=null;
			var timersStop=function(){
				if(obj.ws.reinit){
					clearInterval(obj.ws.reinit);
					clearTimeout(obj.ws.reinit);
					obj.ws.reinit=false;
				}
				if(obj.ws.checking){
					clearInterval(obj.ws.checking);
					clearTimeout(obj.ws.checking);
					obj.ws.checking=false;
				}
			},
			checking=function(time){
				timersStop();
				obj.ws.checking=setTimeout(function(){
					//obj.console.log('check',new Date().getTime());
					console.log('check',new Date().getTime());
					if(obj.ws.readyState==WebSocket.OPEN) {
						obj.ws.send('check');
					}
					obj.ws.reinit=setTimeout(function(){
						obj.console.log('ws reinit');
						if(obj.body && obj.body.responseJSON && obj.body.responseJSON.currentTime<obj.body.responseJSON.endTime) $('#connectionError').addClass('active');
						timersStop();
						if(obj.ws) obj.ws.close();
						obj.ws=false;
						obj.wsConnect();
					},1000);
				},time?time:obj.wsCheckTime);
			};
			
			obj.ws.onmessage = function(evt) {
				if(this.sessionID!=obj.WSsessionID) {
					console.log('wrong session');
					this.close();
					return;
				}
				$('#connectionError').removeClass('active');
				console.log(evt.data);
				//obj.console.log(evt.data);
				var data=JSON.parse(evt.data);
				if(data){
					if(obj.body && obj.body.responseJSON){
						if(data.currentTime) {
							obj.body.responseJSON.currentTime=parseInt(data.currentTime);
							obj.bidControlsCounter=0;
						}
						if(data.lastBid) {
							obj.body.responseJSON.lastBid=data.lastBid;
							obj.lastBid();
						}
						if(data.bidders){
							obj.wsBidders=data.bidders;
							obj.setBiddersOnline(data.bidders);
						}
					}
					if(data.status && data.status=='ok') {
						checking();
						/*if(obj.ws.reinit){
							clearInterval(obj.ws.reinit);
							clearTimeout(obj.ws.reinit);
							//console.log('checked');
						}
						if(obj.ws && obj.ws.checking) obj.ws.checking();*/
					}
				}
				
			};
			obj.ws.onopen = function (evt) {
				$('#connectionError').removeClass('active');
				obj.console.log('ws ok');
			}
			obj.ws.onclose = function (evt) {
				if(this.sessionID!=obj.WSsessionID) return;
				
				var reason="";
				if (evt.code == 1000)
					reason = "Normal closure, meaning that the purpose for which the connection was established has been fulfilled.";
				else if(evt.code == 1001)
					reason = "An endpoint is \"going away\", such as a server going down or a browser having navigated away from a page.";
				else if(evt.code == 1002)
					reason = "An endpoint is terminating the connection due to a protocol error";
				else if(evt.code == 1003)
					reason = "An endpoint is terminating the connection because it has received a type of data it cannot accept (e.g., an endpoint that understands only text data MAY send this if it receives a binary message).";
				else if(evt.code == 1004)
					reason = "Reserved. The specific meaning might be defined in the future.";
				else if(evt.code == 1005)
					reason = "No status code was actually present.";
				else if(evt.code == 1006)
				   reason = "The connection was closed abnormally, e.g., without sending or receiving a Close control frame";
				else if(evt.code == 1007)
					reason = "An endpoint is terminating the connection because it has received data within a message that was not consistent with the type of the message (e.g., non-UTF-8 [http://tools.ietf.org/html/rfc3629] data within a text message).";
				else if(evt.code == 1008)
					reason = "An endpoint is terminating the connection because it has received a message that \"violates its policy\". This reason is given either if there is no other sutible reason, or if there is a need to hide specific details about the policy.";
				else if(evt.code == 1009)
				   reason = "An endpoint is terminating the connection because it has received a message that is too big for it to process.";
				else if(evt.code == 1010) // Note that this status code is not used by the server, because it can fail the WebSocket handshake instead.
					reason = "An endpoint (client) is terminating the connection because it has expected the server to negotiate one or more extension, but the server didn't return them in the response message of the WebSocket handshake. <br /> Specifically, the extensions that are needed are: " + event.reason;
				else if(evt.code == 1011)
					reason = "A server is terminating the connection because it encountered an unexpected condition that prevented it from fulfilling the request.";
				else if(evt.code == 1015)
					reason = "The connection was closed due to a failure to perform a TLS handshake (e.g., the server certificate can't be verified).";
				else
					reason = "Unknown reason";
				obj.console.log('ws closed',evt.code + ': ' + reason);
				if(obj.body && obj.body.responseJSON && obj.body.responseJSON.currentTime<obj.body.responseJSON.endTime) $('#connectionError').addClass('active');
				timersStop();
				//obj.ws=false;
				//obj.wsConnect();
				//obj.bidInit();
				if(evt.code!=1005) checking(100);
			};
			obj.ws.onerror = function (evt) {
				obj.console.log('ws error',evt.message);
			};
			
			checking();
			//var onbeforeunloadFunc=window.onbeforeunload?window.onbeforeunload:false;
			var closing = function(evt){
				if(obj.ws) {
					timersStop();
					obj.ws.onerror = function () {};
					obj.ws.onclose = function () {};
					obj.ws.close();
				}
				//if(onbeforeunloadFunc) onbeforeunloadFunc();
				return null;
			};
			//window.addEventListener('focus',obj.ws.checking,false);
			window.addEventListener('unload',closing,false);
			window.addEventListener('beforeunloadFunc',closing,false);
		}
	},
	getBidValueStart:function(){
		var obj=this;
		if(!obj.body || !obj.body.responseJSON || !obj.body.responseJSON.lastBid || !obj.body.responseJSON.lastBid.length) return false;
		
		var stepTime=(obj.body.responseJSON['source']['settings']['bidStepTime']+obj.body.responseJSON['source']['settings']['bidStepPause'])*obj.body.responseJSON['auctionsSteps'][0].length,
			step=Math.floor((obj.body.responseJSON.currentTime-obj.body.responseJSON['startTime'])/stepTime);
		
		if(step<0) return false;
		
		var start=obj.body.responseJSON['startTime']+step*stepTime+/*obj.body.responseJSON.lastBid[0]['__index__']*/obj.myBidInexes[step+1]*(obj.body.responseJSON['source']['settings']['bidStepPause']+obj.body.responseJSON['source']['settings']['bidStepTime'])+obj.body.responseJSON['source']['settings']['bidStepPause'];
		var lastBid=obj.body.responseJSON.lastBid[0];
		
		for(var i=0;i<obj.body.responseJSON.lastBid.length;i++){
			if(start>parseInt(obj.body.responseJSON.lastBid[i]['ctime'])) lastBid=obj.body.responseJSON.lastBid[i];
			else break;
		}
		return parseFloat(lastBid['value']);
	},
	bidInit:function(){
		var obj=this;
		if(!obj.body || !obj.body.responseJSON || !obj.body.responseJSON.is_bidder) return false;
		//obj.wsConnect();
		if(!$('#bidding').length) {
			$('#body').append($('#bidForm',this.bid).tmpl());
			this.lastBid();
		}
		var inp=$('#bidding form input.js-lastBid_value').unbind();
			price=/^[0-9]+(\.[0-9]{1,2})?$/;
		
		if(!inp[0].cleave){
			inp[0].cleave = new Cleave(inp[0], {
				numeral: true,
				numeralThousandsGroupStyle: 'thousand',
				numeralDecimalMark: '.',
				delimiter: ' ',
				delimiters:[',','.'],
				numeralPositiveOnly: true/*,
				onValueChanged: function (e) {
					console.log(e);
				}*/
			});
		}
		inp.attr('data-value',inp[0].cleave.getRawValue());
		
		inp.change(function(){
			if(!price.test(this.cleave.getRawValue())) {
				this.cleave.setRawValue(this.getAttribute('data-value'));
				//this.value=this.getAttribute('data-value');
				$('.discount',this.form).text('');
				return false;
			}
			var fl=parseFloat(obj.body.responseJSON.lastBid[0].value);
			$('.discount',this.form).text(this.cleave.getRawValue()!=''?number_format((1-parseFloat(this.cleave.getRawValue())/fl)*100,2,'.','')+'%':'');
		})
		.focus(function(){
			obj.console.log('bid input focus',this.value);
		})
		.blur(function(){
			obj.console.log('bid input blur',this.value);
		}).change();
		
		var maxValue=parseFloat((obj.getBidValueStart()-parseFloat(obj.body.responseJSON.source.data.tender.lot.minStep)).toFixed(2));
		if(maxValue<0) maxValue=0;
		
		if(maxValue) $('#bidding').addClass('formExist');
		else $('#bidding').removeClass('formExist');
		
		$('#bidding .bidMaxValue').text(number_format(maxValue,2,'.',' '));
		
		$('#bidding form').unbind().submit(function(){
			if(this.formSending) return false;
			this.formSending=true;
			
			var f=$("[name='data[value]']",this),
				inp=$(".js-lastBid_value",this),
				getBidValueStart=obj.getBidValueStart(),
				val=inp[0].cleave.getRawValue(),
				error=false,
				errors=$('.errors',this);
			
			if(val===''){
				error='empty';
			}
			else if(!price.test(val)){
				error='format';
			}
			else if(parseFloat(val)<=0){
				error='zerro_value';
			}
			else if(parseFloat(val)==parseFloat(obj.body.responseJSON.lastBid[obj.body.responseJSON.lastBid.length-1]['value'])){
				error='same_value';
			}
			else if(parseFloat(val)>parseFloat((getBidValueStart-parseFloat(obj.body.responseJSON.source.data.tender.lot.minStep)).toFixed(2)) && getBidValueStart!=parseFloat(val)){
				error='max_value';
			}
			/*if(!price.test(val) || parseFloat(val)<=0 || parseFloat(val)==parseFloat(obj.body.responseJSON.lastBid[obj.body.responseJSON.lastBid.length-1]['value']) || (parseFloat(val)>parseFloat((getBidValueStart-parseFloat(obj.body.responseJSON.source.data.tender.lot.minStep)).toFixed(2)) && getBidValueStart!=parseFloat(val))){
				//alert('error value');
				alert('<div data-msg="error value" />');
				return false;
			}*/
			if(error){
				$('.error',errors).hide();
				$('.error.'+error,errors).show();
				errors.show();
				inp.parent().addClass('bad');
				obj.console.log('bid error',error);
				this.formSending=false;
				return false;
			}
			inp.parent().removeClass('bad');
			errors.hide();
			if(!obj.isCanBid(obj.body.responseJSON.currentTime)) {
				//alert('error time is end');
				alert('<div data-msg="error time is end" />');
				obj.console.log('bid error','error time is end');
				this.formSending=false;
				return false;
			}
			
			f.val(val);
			
			var form=this;
			var req = new JsHttpRequest(),
				timeout=false;
			req.onreadystatechange = function() {
				if (req.readyState == 4) {
					if(timeout) {
						clearInterval(timeout);
						clearTimeout(timeout);
					}
					if(req.responseJS) {
						obj.console.log('bid send response');
						if(obj.body){
							if(req.responseJS.currentTime) {
								obj.body.responseJSON.currentTime=parseInt(req.responseJS.currentTime);
								obj.bidControlsCounter=0;
							}
							if(req.responseJS.lastBid) {
								obj.body.responseJSON.lastBid=req.responseJS.lastBid;
								obj.lastBid();
							}
						}
						else obj.console.log('auction body is null');
					}
					if(req.responseText!='') {
						if(parseFloat(req.responseText)) alert('<div data-msg="bid error '+req.responseText+'" />');
						else alert(req.responseText);
					}
					form.formSending=false;
				}
			};
			req.open(null, '/auctions/bid', true);
			req.send( {form:this} );
			timeout=setTimeout(function(){
				req.abort();
				obj.console.log('bid send error');
				$('#connectionError').addClass('active');
			},3000);
			obj.console.log('bid send start');
			return false;
		});
	},
	lastBid:function(){
		if(!this.body.responseJSON.lastBid || !this.body.responseJSON.lastBid.length) return false;
		
		var bidStep=$('#bidding').parents('.line.active[data-bid-me]:first'),
			obj=this;
		if(!bidStep.length) return false;
		var bs=bidStep.parents('.bidSteps:first').attr('data-round-index'),
			endTime=parseInt(bidStep.attr('data-bid-end')),
			startTime=parseInt(bidStep.attr('data-bid-start'));
		var log=[],
			d=new Date(),
			beforeStep=false;
		
		var fl=parseFloat(this.body.responseJSON.lastBid[0].value);
		
		for(var i=0;i<this.body.responseJSON.lastBid.length;i++){
			var ctime=parseInt(this.body.responseJSON.lastBid[i].ctime),
				discount=(1-parseFloat(this.body.responseJSON.lastBid[i].value)/fl)*100;
			
			if(ctime>=startTime && ctime<=endTime) {
				var dt={};
				$.extend(true,dt,this.body.responseJSON.lastBid[i]);
				d.setTime(ctime*1000);
				dt.date=d.toString();
				dt.discount=discount;
				log.push(dt);
			}
			else if(ctime<=endTime) {
				beforeStep=this.body.responseJSON.lastBid[i];
				beforeStep.discount=discount;
				//fl=parseFloat(beforeStep.value)
			}
		}
		
		if(beforeStep) {
			var dt={};
			$.extend(true,dt,beforeStep);
			d.setTime(dt.ctime*1000);
			dt.date=d.toString();
			log.unshift(dt);
		}
		if(log.length){
			log[log.length-1].last=1;
			$('#bidding .logs').show();
			$('#bidding .logs .list').html($('#bidLog',this.bid).tmpl(log));
			$('#bidding .logs .list .action a').unbind().bind(clickEvent,function(e){
				e.preventDefault();
				var form=$('#bidding form');
				//$("[name='data[value]']",form).val(this.getAttribute('data-value'));
				$("[name='data[value]']",form)[0].cleave.setRawValue(this.getAttribute('data-value'));
				form.submit();
				return false;
			});
			var data=log[log.length-1],
				getBidValueStart=this.getBidValueStart();
			for(var i in data){
				$('#bidding .js-lastBid_'+i).each(function(){
					var el=$(this);
					if(el.is('input')) {
						var nv=data[i];
						if(getBidValueStart<=parseFloat(data[i])) nv=parseFloat((getBidValueStart-parseFloat(obj.body.responseJSON.source.data.tender.lot.minStep)).toFixed(2));
						if(nv<=0) {
							nv='';
							//data.discount='';
						}
						if(el[0].cleave) el[0].cleave.setRawValue(nv);
						else el.val(nv);
						el.change();
					}
					else el.text(data[i]);
				});
			}
		}
		else $('#bidding .logs').hide();
	},
	bidControlsTimer:null,
	bidControlsCounter:0,
	isCanBidLast:null,
	nextReload:null,
	bidControls:function(){
		var obj=this;
		if(obj.bidControlsTimer){
			clearInterval(obj.bidControlsTimer);
			clearTimeout(obj.bidControlsTimer);
		}
		var canBid=obj.isCanBid(obj.body.responseJSON.currentTime);
		if(canBid) {
			$('#bidding').removeClass('hidden');
			if(!$('#auctionsSteps .bidSteps[data-round-status=live] .bidders [data-bid-me] #bidding').length){
				$('#auctionsSteps .bidSteps[data-round-status=live] .bidders [data-bid-me] .activeBorder').append($('#bidding'));
				this.lastBid();
				obj.console.log('bid form availble');
				obj.console.postImage();
			}
		}
		else {
			$('#bidding').addClass('hidden');
			if(obj.isCanBidLast) {
				obj.isCanBidLast=canBid;
				obj.reload();
				return;
			}
		}
		obj.isCanBidLast=canBid;
		
		obj.bidControlsTimer=setTimeout(function(){
			if(!obj.body) return;
			obj.bidControlsCounter++;
			if(obj.bidControlsCounter==4){
				obj.body.responseJSON.currentTime++;
				obj.switchRoundStatus();
				obj.bidControlsCounter=0;
			}
			if(obj.body.responseJSON.currentTime>obj.body.responseJSON.endTime
			|| (obj.nextReload && (obj.body.responseJSON.currentTime+obj.bidControlsCounter/4)>obj.nextReload)) {
				/*console.log([
					obj.body.responseJSON.currentTime,
					obj.body.responseJSON.endTime,
					obj.nextReload,
					obj.body.responseJSON.currentTime,
					obj.bidControlsCounter/4]);*/
				obj.nextReload=null;
				obj.reload();
				return;
			}
			obj.bidControls();
		},250);
	},
	isCanBid:function(time){
		var obj=this;
		if(!obj.body.responseJSON.is_bidder || obj.body.responseJSON.currentTime>=obj.body.responseJSON.endTime) return false;
		if(!time) time=(new Date()).getTime()/1000;
		
		var stepTime=(obj.body.responseJSON['source']['settings']['bidStepTime']+obj.body.responseJSON['source']['settings']['bidStepPause'])*obj.body.responseJSON['auctionsSteps'][0].length,
			step=Math.floor((time-obj.body.responseJSON['startTime'])/stepTime);
		
		if(step<0) return false;
		var start=obj.body.responseJSON['startTime']+step*stepTime+/*obj.body.responseJSON.lastBid[0]['__index__']*/obj.myBidInexes[step+1]*(obj.body.responseJSON['source']['settings']['bidStepPause']+obj.body.responseJSON['source']['settings']['bidStepTime'])+obj.body.responseJSON['source']['settings']['bidStepPause'];
		
		if(time<start || time>(start+obj.body.responseJSON['source']['settings']['bidStepTime'])) return false;
		return true;
	},
	isBidStepEnd:function(time){
		var obj=this;
		if(!time) time=(new Date()).getTime()/1000;
		
		var stepTime=(obj.body.responseJSON['source']['settings']['bidStepTime']+obj.body.responseJSON['source']['settings']['bidStepPause'])*obj.body.responseJSON['auctionsSteps'][0].length,
			step=Math.floor((time-obj.body.responseJSON['startTime'])/stepTime);
		
		if(step<0) return false;
		var start=obj.body.responseJSON['startTime']+step*stepTime+obj.body.responseJSON.lastBid['__index__']*(obj.body.responseJSON['source']['settings']['bidStepPause']+obj.body.responseJSON['source']['settings']['bidStepTime'])+obj.body.responseJSON['source']['settings']['bidStepPause'];
		if(time<start || time>(start+obj.body.responseJSON['source']['settings']['bidStepTime'])) return false;
		return true;
	},
	timerDownClock:false,
	localCurrentTime:(new Date()).getTime()/1000,
	timerDown:function(){
		if(!this.body) return;
		var obj=this,
			tenderBidEnd=obj.body.responseJSON.startTime-obj.body.responseJSON.currentTime;
		
		if(tenderBidEnd<=0) {
			return;
		}
		if(obj.timerDownClock) clearInterval(obj.timerDownClock);
		
		today = tenderBidEnd;
		tsec=today%60; today=Math.floor(today/60); if(tsec<10)tsec='0'+tsec;
		tmin=today%60; today=Math.floor(today/60); if(tmin<10)tmin='0'+tmin;
		thour=today%24; today=Math.floor(today/24);
		timestr=[];
		/*if(today) */timestr.push('<span class="counter countDays">'+today+' <span class="counterTxt" data-msg="timer days"></span></span>');
		/*if(thour || today) */timestr.push('<span class="counter countHours">'+thour+' <span class="counterTxt" data-msg="timer hours"></span></span>');
		/*if(parseInt(tmin) || thour || today) */timestr.push('<span class="counter countMins">'+tmin+' <span class="counterTxt" data-msg="timer minutes"></span></span>');
		timestr.push('<span class="counter countSecs">'+tsec+' <span class="counterTxt" data-msg="timer seconds"></span></span>');
		timestr=timestr.join(' ');
		
		var localCurrentTime=(new Date()).getTime()/1000,
			diff=localCurrentTime-obj.localCurrentTime;
		obj.localCurrentTime=localCurrentTime;
		if(diff<1) diff=1;
		
		obj.body.responseJSON.currentTime=Math.round(obj.body.responseJSON.currentTime+diff);
		$('.timerCounters').html(timestr);
		obj.timerDownClock=setTimeout(function(){obj.timerDown();},1000);
	},
	setBiddersOnline:function(bidders){
		for(var i=0;i<bidders.length;i++) bidders[i]=bidders[i].toString();
		$('#auctionsSteps .bidStep_0 [data-bid-id]').each(function(){
			var el=$(this);
			if($.inArray(el.attr('data-bid-id'),bidders)==-1) $('.status',el).html('<span class="offline" data-msg="bidder offline" />');
			else $('.status',el).html('<span class="online" data-msg="bidder online" />');
		});
	},
	setTimerCount:function(el,today,total){
		if(total) $('.timeLine .dynamic',el).css('width',(100-(today/total*100))+'%').addClass('animate');
		tsec=today%60; today=Math.floor(today/60); if(tsec<10)tsec='0'+tsec;
		tmin=today%60; today=Math.floor(today/60); if(tmin<10)tmin='0'+tmin;
		timestr=[];
		//timestr.push('<span class="counter countMins">'+tmin+' <span class="counterTxt" data-msg="timer minutes"></span></span>');
		//timestr.push('<span class="counter countSecs">'+tsec+' <span class="counterTxt" data-msg="timer seconds"></span></span>');
		timestr.push('<span class="counter countMins">'+tmin+'</span>');
		timestr.push('<span class="counter countSecs">'+tsec+'</span>');
		timestr=timestr.join(':');
		if(!el) return timestr;
		$('.timerCounters',el).html(timestr);
	},
	switchRoundStatus:function(){
		if(!$('#auctionsSteps .bidSteps').length) return false;
		var obj=this;
		if(obj.body.responseJSON.currentTime<obj.body.responseJSON.endTime){
			var stepTime=(obj.body.responseJSON['source']['settings']['bidStepTime']+obj.body.responseJSON['source']['settings']['bidStepPause'])*obj.body.responseJSON['auctionsSteps'][0].length,
				step=Math.floor((obj.body.responseJSON.currentTime-obj.body.responseJSON['startTime'])/stepTime),
				lastStep=$('#auctionsSteps .bidSteps[data-round-status=live]');
			if(lastStep.length) lastStep=parseInt(lastStep.attr('data-round-index'))-1;
			else lastStep=0;
			
			if(lastStep!=step) obj.console.log('switch to round',step);
			
			$('#auctionsSteps .bidSteps').each(function(){
				var index=parseInt(this.getAttribute('data-round-index'))-1,
					el=$(this);
				if(index>=0 && index<4){
					if(step==index) {
						var pause=stepTime*index+obj.body.responseJSON['source']['settings']['bidStepPause']+obj.body.responseJSON['startTime']-obj.body.responseJSON.currentTime;
						if(pause<=0){
							if(el.attr('data-round-status')!='live'){
								obj.console.log('round status','live');
							}
							el.attr('data-round-status','live');
							obj.setTimerCount($('.roundTimeline',el),stepTime*(index+1)+obj.body.responseJSON['startTime']-obj.body.responseJSON.currentTime,stepTime-obj.body.responseJSON['source']['settings']['bidStepPause']);
							
							if(index<3){
								var ba=$('.bidders .line.active[data-bid-id]',el);
								$('.bidders .line[data-bid-id]',el).removeClass('active').each(function(){
									var bid_start=parseInt(this.getAttribute('data-bid-start'));
									/*var d=new Date;
									d.setTime(obj.body.responseJSON.currentTime*1000);
									console.log('current',d.toString());
									d.setTime(bid_start*1000);
									console.log('start',d.toString());*/
									if(obj.body.responseJSON.currentTime<bid_start) return;
									var bid_end=parseInt(this.getAttribute('data-bid-end')),
										bid_endpause=parseInt(this.getAttribute('data-bid-endpause'));
									
									$('.pauseTime',this).hide();
									
									if(obj.body.responseJSON.currentTime<bid_end){
										obj.setTimerCount($('.bidTime',this),bid_end-obj.body.responseJSON.currentTime);
										$(this).addClass('active');
										
										if(!ba.length || ba.attr('data-bid-id')!=this.getAttribute('data-bid-id')){
											obj.console.log('bidder status','active',this.getAttribute('data-bid-id'));
										}
									}
									else if(obj.body.responseJSON.currentTime<=bid_endpause && !$(this).is(':last-child')){
										obj.setTimerCount($('.pauseTime',this).show(),bid_endpause-obj.body.responseJSON.currentTime,obj.body.responseJSON['source']['settings']['bidStepPause']);
										if(obj.body.responseJSON.currentTime>=bid_endpause) $('.pauseTime',this).hide();
										
										if(!$(this).hasClass('bid_complete')){
											obj.console.log('bidder status','active',this.getAttribute('data-bid-id'));
										}
										
										$(this).addClass('bid_complete');
									}
									else {
										if(!$(this).hasClass('bid_complete')){
											obj.console.log('bidder status','active',this.getAttribute('data-bid-id'));
										}
										$(this).addClass('bid_complete');
									}
								});
							}
						}
						else {
							el.attr('data-round-status','pending.active');
							obj.setTimerCount($('.roundTimeline',el),pause,obj.body.responseJSON['source']['settings']['bidStepPause']);
						}
					}
					else if(step<index) {
						$('.roundTimeline .timerCounters',el).text('');
						el.attr('data-round-status','pending');
					}
					else {
						$('.roundTimeline .timerCounters',el).text('');
						if(el.attr('data-round-status')!='complete'){
							obj.console.log('round status','complete');
						}
						el.attr('data-round-status','complete');
					}
				}
			});
		}
		else {
			$('#auctionsSteps .bidSteps').attr('data-round-status','complete');
		}
	},
	query:false,
	parseQuery:function(){
		if(this.query!==false) return this.query;
		var q=window.location.search.substr(1, window.location.search.length-1).split('&');
		this.query={};
		
		for(var i=0;i<q.length;i++){
			var j=q[i].split('=');
			this.query[j[0]]=j[1];
		}
		return this.query;
	},
	initLang:function(){
		$('.langs [data-lang]').unbind().bind(clickEvent,function(e){
			e.preventDefault();
			if(!this.getAttribute('data-lang')) return false;
			$('.langs [data-lang]').removeClass('active');
			$('html').attr('lang',this.getAttribute('data-lang'));
			$(this).addClass('active');
			return false;
		});
		
		if(!$('html').attr('lang') && $('.langs [data-lang]').length) {
			
			var query=this.parseQuery();
			
			var aLang=false;
			if(query.lang) aLang=$(".langs [data-lang='"+query.lang+"']");
			if(!aLang || !aLang.length) aLang=$('.langs [data-lang]:first');
			
			aLang.trigger(clickEvent);
		}
		else {
			$(".langs [data-lang='"+$('html').attr('lang')+"']").trigger(clickEvent);
		}
	},
	console:{
		//logCache:[],
		log:function(){
			if(!arguments) return;
			var serverTime='unknown',
				localTime=(new Date()).getTime();
			if(auction.body && auction.body.responseJSON && auction.body.responseJSON.currentTime) serverTime=auction.body.responseJSON.currentTime;
			var data=JSON.stringify({data:arguments,serverTime:serverTime,localTime:localTime});
			//this.logCache.push(data);
			
			if(window.sessionStorage[this.getKey()+'_sign']){
				window.sessionStorage[this.getKey()+'_sign']=sha384(window.sessionStorage[this.getKey()+'_sign']+data);
				data=JSON.stringify({logRecord:data,sign:window.sessionStorage[this.getKey()+'_sign']});
			}
			
			if(window.sessionStorage[this.getKey()] === undefined) window.sessionStorage[this.getKey()]='';
			window.sessionStorage[this.getKey()]+=data+"\r\n";
			console.log(data);
			$('.logSave').addClass('active');
		},
		save:function(){
			var text = window.sessionStorage[this.getKey()];
			
			var blob = new Blob([text], {type: "text/plain;charset=utf-8"});
			saveAs(blob, "logs_"+auction.auctionHash.get()+".log");
		},
		init:function(){
			var obj=this;
			$('.logSave').unbind().bind(clickEvent,function(e){
				e.preventDefault();
				obj.save();
				return false;
			});
			
			if(window.sessionStorage[this.getKey()]) {
				$('.logSave').addClass('active');
				window.onbeforeunload = function(e){
					auction.console.log('close tab confirm');
					var msgs={
							'default':'Confirm close window'
						},
						message = msgs[$('html').attr('lang')]?msgs[$('html').attr('lang')]:msgs['default'];
					if (typeof e == "undefined") {
						e = window.event;
					}
					if (e) {
						e.returnValue = message;
					}
					return message;
				};
			}
		},
		clear:function(){
			delete window.sessionStorage[this.getKey()];
			delete window.sessionStorage[this.getKey()+'_sign'];
			$('.logSave').removeClass('active');
		},
		getKey:function(){
			return auction.auctionHash.get()+'_console';
		},
		setSign:function(sign){
			if(!sign || window.sessionStorage[this.getKey()+'_sign']){
				return;
			}
			window.sessionStorage[this.getKey()+'_sign']=sign;
		},
		getSign:function(){
			return window.sessionStorage[this.getKey()+'_sign'];
		},
		haveLogs:function(){
			return window.sessionStorage[this.getKey()];
		},
		postImage:function(){
			if(!window['html2canvas']) return;
			var obj=this,
				w=$(window).width(),
				h=$(window).height()
				y=$(window).scrollTop();
			html2canvas(document.body, {
				async:true,
				allowTaint:true,
				foreignObjectRendering:true,
				//backgroundColor:'grey',
				onrendered: function(canvas) {
					var ctx = canvas.getContext("2d");
					ctx.strokeStyle = "red";
					ctx.lineWidth = 3;
					ctx.strokeRect(3, y, w-6, h-6);
					var canvasData = canvas.toDataURL("image/png").split(',')[1];
					obj.log('screenshot',canvasData);
					$('html,body').scrollTop(y);
				}
			});
		}
	},
	tab:{
		init:function(){
			if(this.id) return;
			var s=auction.auctionHash.get()+'_tab';
				id=window.sessionStorage[s];
			if(id) {
				this.id=id;
				return;
			}
			window.sessionStorage[s]=this.genID();
			this.id=window.sessionStorage[s];
		},
		genID:function(){
			return 'tab_' + Math.random().toString(36).substr(2, 9);
		},
		getID:function(){
			this.init();
			return this.id
		}
	},
	auctionHash:{
		init:function(){
			if(this.hash) return;
			this.hash=sha512_256(window.location.pathname);
		},
		get:function(){
			this.init();
			return this.hash;
		}
	}
};
function alert(msg){
	$.fn.jAlert({
		'title': '',
		'message': msg, 
		//'clickAnywhere': true
		'closeBtn': false,
		'btn': [
			{'label':'',msg:"jAlert OK", 'closeOnClick': true }
		],
		onOpen:function(alertEl){
			$(document.activeElement).blur();
			setTimeout(function(){$("[data-msg='jAlert OK']",alertEl).focus();},1000);
		}
	});
}
function confirm(msg,onConfirm,onClose){
	$.fn.jAlert({
		'title': '',
		'message': msg, 
		//'clickAnywhere': true
		'closeBtn': false,
		'btn': [
			{'label':'',msg:"jAlert OK", 'cssClass': 'green', 'closeOnClick': true, 'onClick': function(e){if(e){if(e[0].jAlertClicked){return false;}e[0].jAlertClicked=true;}if(onConfirm)onConfirm();} },
			{'label':'',msg:"jAlert cancel", 'cssClass': 'red', 'closeOnClick': true, 'onClick': function(e){if(e){if(e[0].jAlertClicked){return false;}e[0].jAlertClicked=true;}if(onClose)onClose();} }
		],
		onClose:function(e){
			if(e && e[0].jAlertClicked){e[0].jAlertClicked=false;}
		}
	});
}