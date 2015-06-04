<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<title>Solarwinds Graphs</title>
<!--[if lt IE 9]><script language="javascript" type="text/javascript" src="jqplot/excanvas.js"></script><![endif]-->
<script language="javascript" type="text/javascript" src="jqplot/jquery.min.js"></script>
<script language="javascript" type="text/javascript" src="jqplot/jquery.jqplot.min.js"></script>
<script language="javascript" type="text/javascript" src="jqplot/plugins/jqplot.dateAxisRenderer.min.js"></script>
<script language="javascript" type="text/javascript" src="jqplot/plugins/jqplot.canvasAxisTickRenderer.min.js"></script> 
<script language="javascript" type="text/javascript" src="jqplot/plugins/jqplot.canvasTextRenderer.min.js"></script>
<link rel="stylesheet" type="text/css" href="jqplot/jquery.jqplot.css" />
</head>
<body>
<div id="chartdiv_24h" style="height:600px;width:900px; "></div>
<hr />
<div id="chartdiv_7d" style="height:600px;width:900px; "></div>
<hr />
<div id="chartdiv_30d" style="height:600px;width:900px; "></div>
<%@ page import="java.util.*" %>
<%@ page import="java.text.*" %>
<%@ page import="javax.management.timer.Timer" %>
<%@ page import="javax.sql.*;" %>
<% 

// Get Parameters
String ipaddr=request.getParameter("ipaddr");
String port=request.getParameter("port");

// Get current datetime and differentials
Date now = new Date();
Date now_24h = new Date(now.getTime() - 1L * Timer.ONE_DAY);
Date now_7d = new Date(now.getTime() - 1L * Timer.ONE_WEEK);
Date now_30d = new Date(now.getTime() - 30L * Timer.ONE_DAY);

// Set Date Format and Time Zone
DateFormat dfm = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss");
dfm.setTimeZone(TimeZone.getTimeZone("America/Chicago"));

java.sql.Connection con,con7,con30;
java.sql.Statement s,s7,s30;
java.sql.ResultSet rs,rs7,rs30;
java.sql.PreparedStatement pst;

con=null;
con7=null;
//con30=null;
s=null;
s7=null;
//s30=null;
pst=null;
rs=null;
rs7=null;
//rs30=null;

// SQL Connection
String url= "jdbc:jtds:sqlserver://204.9.216.36/BHDC_Network";
String id= "bhpportal";
String pass = "DataCom!";
try{

Class.forName("net.sourceforge.jtds.jdbc.Driver");
con = java.sql.DriverManager.getConnection(url, id, pass);
con7 = java.sql.DriverManager.getConnection(url, id, pass);
//con30 = java.sql.DriverManager.getConnection(url, id, pass);

}catch(ClassNotFoundException cnfex){
cnfex.printStackTrace();

}
String sql = "select * from (select row_number() over (order by DateTime) as RN,[ITD].NodeID, [ITD].InterfaceID, [Nodes].IP_Address, [ITD].DateTime, [ITD].In_Averagebps, [ITD].Out_Averagebps from InterfaceTraffic_Detail as ITD inner join Nodes on [Nodes].NodeID=[ITD].NodeID inner join Interfaces on ([Interfaces].InterfaceID=[ITD].InterfaceID and [Interfaces].InterfaceIndex="+port+") where IP_Address='"+ipaddr+"' and DateTime >= '"+dfm.format(now_24h)+"') T1 where RN%2=0 order by InterfaceID,DateTime asc;";
String sql7d = "select * from (select row_number() over (order by DateTime) as RN,[ITD].NodeID, [ITD].InterfaceID, [Nodes].IP_Address, [ITD].DateTime, [ITD].In_Averagebps, [ITD].Out_Averagebps from InterfaceTraffic_Detail as ITD inner join Nodes on [Nodes].NodeID=[ITD].NodeID inner join Interfaces on ([Interfaces].InterfaceID=[ITD].InterfaceID and [Interfaces].InterfaceIndex="+port+") where IP_Address='"+ipaddr+"' and DateTime >= '"+dfm.format(now_7d)+"') T1 where RN%4=0 order by InterfaceID,DateTime asc;";
//String sql7d = "select [ITD].NodeID, [ITD].InterfaceID, [Nodes].IP_Address, [ITD].DateTime, [ITD].In_Averagebps, [ITD].Out_Averagebps from InterfaceTraffic_Hourly as ITD left join Nodes on [Nodes].NodeID=[ITD].NodeID where IP_Address='"+ipaddr+"' and DateTime >= '"+dfm.format(now_7d)+"' order by DateTime,NodeID,InterfaceID asc;";
//String sql30d = "select [ITD].NodeID, [ITD].InterfaceID, [Nodes].IP_Address, [ITD].DateTime, [ITD].In_Averagebps, [ITD].Out_Averagebps from InterfaceTraffic_Daily as ITD left join Nodes on [Nodes].NodeID=[ITD].NodeID where IP_Address='"+ipaddr+"' and DateTime >= '"+dfm.format(now_30d)+"' order by DateTime,NodeID,InterfaceID asc;";
try{
s = con.createStatement();
s7 = con7.createStatement();
//s30 = con30.createStatement();
rs = s.executeQuery(sql);
rs7 = s7.executeQuery(sql7d);
//rs30 = s30.executeQuery(sql30d);
%>

<%
String outpts_24h = "";
String outpts_7d = "";
Integer i1=0;
Integer i7=0;
//Integer i30=0;
//String outpts_30d = "";
while( rs.next() ) { outpts_24h = outpts_24h + "['" + rs.getString("DateTime") + "'," + rs.getString("Out_Averagebps") + "],"; i1++; };
while( rs7.next() ){ outpts_7d = outpts_7d + "['" + rs7.getString("DateTime") + "'," + rs7.getString("Out_Averagebps") + "],"; i7++; };
//while( rs30.next() ){ if(i30%32==0) {outpts_30d = outpts_30d + "['" + rs30.getString("DateTime") + "'," + rs30.getString("Out_Averagebps") + "],";}; i30++; };
outpts_24h = outpts_24h.substring(0, outpts_24h.length()-1);
outpts_7d = outpts_7d.substring(0, outpts_7d.length()-1);
//outpts_30d = outpts_30d.substring(0, outpts_30d.length()-1);
%>
<script language="javascript" type="text/javascript">
var out_points_24h = [<%= outpts_24h %>];
var out_points_7d = [<%= outpts_7d %>];
<%
rs = s.executeQuery(sql);
rs7 = s7.executeQuery(sql7d);
//rs30 = s30.executeQuery(sql30d);
String inpts_24h = "";
String inpts_7d = "";
i1 = 0;
i7 = 0;
//i30 = 0;
//String inpts_30d = "";
while( rs.next() ) {inpts_24h = inpts_24h + "['" + rs.getString("DateTime") + "'," + rs.getString("In_Averagebps") + "],"; i1++; };
while( rs7.next() ){ inpts_7d = inpts_7d + "['" + rs7.getString("DateTime") + "'," + rs7.getString("In_Averagebps") + "],"; i7++; };
//while( rs30.next() ){ if(i30%32==0) {inpts_30d = inpts_30d + "['" + rs30.getString("DateTime") + "'," + rs30.getString("In_Averagebps") + "],";}; i30++; };
inpts_24h = inpts_24h.substring(0, inpts_24h.length()-1);
inpts_7d = inpts_7d.substring(0, inpts_7d.length()-1);
//inpts_30d = inpts_30d.substring(0, inpts_30d.length()-1);
%>
var in_points_24h = [<%= inpts_24h %>];
var in_points_7d = [<%= inpts_7d %>];

$(document).ready(function(){
  var plot_24h = $.jqplot ('chartdiv_24h',[out_points_24h,in_points_24h],
    { title:'Bandwidth Usage - Last 24 Hours',
	  stackSeries:true,
	  legend:{show:true}, 
	  series:[{color:'rgba(255,0,0,0.8)',label:'Upload',showMarker:false,fill:true},{color:'rgba(0,0,255,0.8)',label:'Download',showMarker:false,fill:true}], 
	  axes:{ xaxis:{renderer:$.jqplot.DateAxisRenderer, rendererOptions:{tickRenderer:$.jqplot.CanvasAxisTickRenderer},tickOptions:{fontSize:'10pt',fontFamily:'Tahoma',angle:-90},label:'Date'},
	         yaxis:{min:0,tickOptions:{formatter: function(format, value) { return (value/1000).toFixed(2) + " kbps"; }},label:'Bandwidth'} } 
	  }); 
	  
  var plot_7d = $.jqplot ('chartdiv_7d',[out_points_7d,in_points_7d],
    { title:'Bandwidth Usage - Last 7 Days', 
	  stackSeries:true,
	  legend:{show:true}, 
	  series:[{color:'rgba(255,0,0,0.8)',label:'Upload',showMarker:false,fill:true},{color:'rgba(0,0,255,0.8)',label:'Download',showMarker:false,fill:true}],
	  axes:{ xaxis:{renderer:$.jqplot.DateAxisRenderer, rendererOptions:{tickRenderer:$.jqplot.CanvasAxisTickRenderer},tickOptions:{fontSize:'10pt',fontFamily:'Tahoma',angle:-90},label:'Date'},
	         yaxis:{min:0,tickOptions:{formatter: function(format, value) { return (value/1000).toFixed(2) + " kbps"; }},label:'Bandwidth'} } 
	  });

/*  var plot_30d = $.jqplot ('chartdiv_30d',[out_points_30d,in_points_30d],
    { title:'Bandwidth Usage - Last 30 Days',
      stackSeries:true,	
	  legend:{show:true}, 
	  series:[{color:'rgba(255,0,0,0.8)',label:'Upload',showMarker:false,fill:true},{color:'rgba(0,0,255,0.8)',label:'Download',showMarker:false,fill:true}],
	  axes:{ xaxis:{renderer:$.jqplot.DateAxisRenderer, rendererOptions:{tickRenderer:$.jqplot.CanvasAxisTickRenderer},tickOptions:{fontSize:'10pt',fontFamily:'Tahoma',angle:-90},label:'Date'},
	         yaxis:{min:0,tickOptions:{formatter: function(format, value) { return (value/1000).toFixed(2) + " kbps"; }},label:'Bandwidth'} } 
	  });
*/ 	  
	});
</script>
<%

}
catch(Exception e){e.printStackTrace();}
finally{
if(rs!=null) rs.close();
if(rs7!=null) rs7.close();
//if(rs30!=null) rs30.close();
if(s!=null) s.close();
if(s7!=null) s7.close();
//if(s30!=null) s30.close();
if(con!=null) con.close();
if(con7!=null) con7.close();
//if(con30!=null) con30.close();
}

%>

</body>
</html>
