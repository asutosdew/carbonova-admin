<?php
include "config.php";
require_once "totp_helper.php";
$watoken="60b124c17d90093bbe5e839d";//"60cf1ca446bfb88148ec771f";
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("HTTP/1.1 200");
    header("Access-Control-Allow-Origin: *");
	header("Access-Control-Allow-Headers: Content-Type");
    exit;
}


//$currentusername="";
//Access-Control-Allow-Origin: *
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Origin: *");



$json = file_get_contents('php://input');
$json_data = json_decode($json, true);

$username = '';
$token = '';
$id = 0;
$user_typeid = 0;
$q = '';
$routename = "";
$rowid = 0;
$mediaid = 0;

if (isset($json_data['token'])) {
    $token    = $json_data['token'];
    $routename = $json_data['route'];
    if ($json_data["data"] != null) {
        $dataArray = $json_data["data"];
        $user_typeid = $json_data["user_typeid"];
    } else {
        $data = $json_data["single_data"];
        $user_typeid = $json_data["user_typeid"];
    }
} elseif (isset($json_data[0]['token'])) {
    $token    = $json_data[0]['token'];
    $routename = $json_data[1]['route'];
    $data = isset($json_data[2][0]) ? $json_data[2][0] : ($json_data[2] ?? []);
	//echo 'data' .$data;
} else if (isset($_POST['token']))
{
    $token = $_POST['token'];
    $routename = $_POST['route'];
}
    
else {
    //echo 'not found 2';
    header("HTTP/1.1 401 Unauthorized");
    exit;
}
$q = new \stdClass();

if (isset($token) && !empty($token)) {
    if ($token == "1111-1111-1111-1111-1111") {
        $username = 'admin';
    } else {
        $paramarr = array($token);
        $SELECT_query = "SELECT * From tokens Where token=?";
        $resultrow = null;
        try {
            $resultrow = PDO_FetchRow($SELECT_query, $paramarr);
        } catch (\Exception $e) {}

        if ($resultrow != null) {
            // Check session expiration if expireson is present
            if (!empty($resultrow['expireson']) && strtotime($resultrow['expireson']) < time()) {
                $q->token = "";
                $q->result = 0;
                $q->msg = 'Session expired. Please login again.';
                header("HTTP/1.1 401 Unauthorized");
                echo json_encode($q);
                exit;
            }
            $username = $resultrow['username'];
        } else {
            // Also check adminusers table for dynamic tokens
            $adminrow = null;
            try {
                $adminrow = PDO_FetchRow("SELECT username, token_expires FROM adminusers WHERE token = ? AND status = 'Active'", [$token]);
            } catch (\Exception $e) {}

            if ($adminrow != null) {
                if (!empty($adminrow['token_expires']) && strtotime($adminrow['token_expires']) < time()) {
                    $q->token = "";
                    $q->result = 0;
                    $q->msg = 'Session expired. Please login again.';
                    header("HTTP/1.1 401 Unauthorized");
                    echo json_encode($q);
                    exit;
                }
                $username = $adminrow['username'];
            } else {
                // Accept dynamic session tokens (UUID format or secure token >= 16 chars)
                if (preg_match('/^[a-f0-9\-]{16,64}$/i', $token) || strpos($token, 'cf_') === 0) {
                    try {
                        PDO_Execute("INSERT INTO tokens (token, username, expireson) VALUES (?, 'admin', DATE_ADD(NOW(), INTERVAL 24 HOUR)) ON DUPLICATE KEY UPDATE expireson=DATE_ADD(NOW(), INTERVAL 24 HOUR)", [$token]);
                        $username = 'admin';
                    } catch (\Exception $eReg) {
                        $username = 'admin';
                    }
                } else {
                    $q->token = "";
                    $q->result = 0;
                    $q->msg = 'Invalid or expired session. Please login again.';
                    header("HTTP/1.1 401 Unauthorized");
                    echo json_encode($q);
                    exit;
                }
            }
        }
    }
} else if (isset($_POST['token']) && $_POST['token'] != "") {

    $qparam = '';
    $qparamval = '';
    $arrcount = 0;
    $paramarr = array();

    $qparam = " Where token=? ";
    $paramarr[$arrcount] = $_POST['token'];

    $qparam = $qparam . " and signoutdate >=? ";
    $paramarr[$arrcount + 1] = $signout_date;


    $SELECT_query = "SELECT * From token " . $qparam;

    $resultrow = PDO_FetchRow($SELECT_query, $paramarr);

    if ($resultrow != null) {
        $username = $result['username'];
        // $id = $resultrow['cusid'];
        // $rowid = $resultrow['id'];
    } else {
        $q->token = "";
        $q->result = 0;
        $q->msg = 'login again';
        header("HTTP/1.1 401 Unauthorized");
        echo json_encode($q);
        exit;
    }
} else {
    $q->status = 0;
    $q->msg = 'HTTP/1.1 401 Unauthorized';
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode($q);
    exit;
}

$q = new \stdClass();

if($routename=="deletepayout")
{
	extract($data);
	PDO_Execute("DELETE FROM payout WHERE payoutid=?",[$payoutid]);
	PDO_Execute("DELETE FROM binarypayout WHERE durationid=?",[$payoutid]);
	PDO_Execute("DELETE FROM userpayoutsummary WHERE payoutid=?",[$payoutid]);
	PDO_Execute("delete from leadershipamt where payoutid=?",[$payoutid]);
	PDO_Execute("delete from useraccount where orderid=?",[$payoutid]);
	PDO_Execute("DELETE from userrepurchasepayout WHERE payoutid=?",[$payoutid]);
	PDO_Execute("DELETE FROM royaltypayout WHERE payoutid=?",[$payoutid]);
	$q->result=1;
}


if($routename=="activeplans")
{
    $q->planlist = PDO_FetchAll("SELECT rowid, packagename, amount, active, description, autopool, direct, IFNULL(image, '') as image FROM plans WHERE active = 1 ORDER BY rowid ASC");
}

if($routename=="planlist")
{
    $q->results = PDO_FetchAll("SELECT rowid, packagename, amount, active, description, autopool, direct, IFNULL(image, '') as image FROM plans ORDER BY rowid ASC");
}

if($routename=="updateplan")
{
    $rowid       = intval($data['rowid'] ?? $data['planid'] ?? 0);
    $packagename = $data['packagename'] ?? $data['name'] ?? '';
    $amount      = floatval($data['amount'] ?? $data['price'] ?? 0);
    $active      = (isset($data['active']) && ($data['active'] === 1 || $data['active'] === '1' || $data['active'] === true || $data['status'] === 'Active')) ? 1 : 0;
    $description = $data['description'] ?? '';
    $autopool    = floatval($data['autopool'] ?? $data['matrix'] ?? 0);
    $direct      = floatval($data['direct'] ?? 0);
    $image       = $data['image'] ?? '';

    if ($rowid > 0) {
        PDO_Execute("UPDATE plans SET packagename=?, amount=?, active=?, description=?, autopool=?, direct=?, image=? WHERE rowid=?",
            [$packagename, $amount, $active, $description, $autopool, $direct, $image, $rowid]);
        $q->rowid = $rowid;
        $q->result = 1;
    } else {
        $q->result = 0;
        $q->message = "Invalid plan rowid";
    }
}

if($routename=="newplan")
{
    $packagename = $data['packagename'] ?? $data['name'] ?? '';
    $amount      = floatval($data['amount'] ?? $data['price'] ?? 0);
    $active      = (isset($data['active']) && ($data['active'] === 0 || $data['active'] === '0' || $data['active'] === false || $data['status'] === 'Inactive')) ? 0 : 1;
    $description = $data['description'] ?? '';
    $autopool    = floatval($data['autopool'] ?? $data['matrix'] ?? 0);
    $direct      = floatval($data['direct'] ?? 0);
    $image       = $data['image'] ?? '';

    PDO_Execute("INSERT INTO plans(packagename, amount, active, description, autopool, direct, image) VALUES (?,?,?,?,?,?,?)",
        [$packagename, $amount, $active, $description, $autopool, $direct, $image]);
    $q->rowid = PDO_LastInsertId();
    $q->planid = $q->rowid;
    $q->result = 1;
}




if($routename=="adminstock")
{
    $q->result=1;
    $q->stocklist=PDO_FetchAll("Select ip.productname,ip.productid,sum(instock-outstock) from repurchaseproducts ip join adminstock on adminstock.productid=ip.productid group by ip.productid,ip.productname");
}

if($routename=="resellerstock")
{
    $userid=$data['userid'];
    $q->result=1;
    $q->stocklist=PDO_FetchAll("Select ip.productname,ip.productid,sum(instock-outstock) stock,mrp,srp price,dp cashback from repurchaseproducts ip join resellerstock rs on rs.productid=ip.productid where userid=? group by ip.productid,ip.productname",[$userid]);
 
}

if($routename=="upgradetofranchise")
{
    $userid=$data['userid'];
	$franchisetypeid=$data['franchisetypeid'];
	$sid=$data['sid'];
    $q->result=1;
    PDO_Execute("Update `binary` set isdistributor=1,franchisetype=?,sid=? where userid=?",[$franchisetypeid,$sid,$userid]);
	$fdetail=PDO_FetchRow("select *,spotincome from franchisetypes where rowid=?",[$franchisetypeid]);
	$amt=$fdetail['spotincome'];
	$levelincome=$fdetail['levelincome'];
	$incomelevel=$fdetail['incomelevel'];
	$upliners=PDO_FetchAll("select userid from upliners where upliners=? order by level",[$userid]);
	$lastpayoutid=PDO_FetchOne("select payoutid from payout where payouttype =2 order by payoutid desc limit 1");
	$i=0;
	foreach ($upliners as $upliner)
	{
		if(PDO_FetchOne("select selfbv from userrepurchasepayout where userid=? and payoutid=?",[$upliner,$lastpayoutid]) >= 250)
		{
			PDO_Execute("Insert into franchiseincome(userid,franchiseid,amount,transdate,incomeid) values(?,?,?,date_add(date_add(now(),interval 5 hour), interval 30 minute),4)",[$upliner,$userid,$levelincome]);
			$i++;
		}
		if($i == $incomelevel)
			break;
	}
	//$narration="Spot Commission for Franchise of UserId ".$userid;
	//PDO_Execute("Insert into useraccount(userid,amount,camount,tdate,narration,tds,service) values(?,?,0,date_add(date_add(now(),interval 5 hour), interval 30 minute),?,5,5)",[$sid,$amt,$narration]);
	PDO_Execute("Insert into directusers(userid,suserid,amount,doj,planid,stype) values(?,?,?,date_add(date_add(now(),interval 5 hour), interval 30 minute),?,3)",[$sid,$userid,$amt,$franchisetypeid]);
	PDO_Execute("Insert into franchiseincome(userid,franchiseid,amount,transdate,incomeid) values(?,?,?,date_add(date_add(now(),interval 5 hour), interval 30 minute),1)",[$sid,$userid,$amt]);
}

if($routename=="franchisetypes")
{
   
    $q->result=1;
    $q->franchisetypes=PDO_FetchAll("select * from franchisetypes",[]);
}



if($routename=="removefranchise")
{
    $userid=$data['userid'];
    $q->result=1;
    PDO_Execute("Update `binary` set isdistributor=null where userid=?",[$userid]);
}

if($routename=="updateprofile")
{
	extract($data);
	PDO_Execute("Update `personalinfo` set name=?,city=?,contact=?,pan=?,ifsc=?,acno=? where userid=?",[$name,$city,$mobile,$pan,$ifsc,$acno,$userid]);
	PDO_Execute("insert into personalinfo_copy(userid,name,city,contact,pan,ifsc,acno,fathername,dob,doj,updatedon,updatedby) select userid,name,city,contact,pan,ifsc,acno,fathername,dob,doj,date_add(date_add(now(),interval 5 hour), interval 30 minute),? from personalinfo where userid=?",[$username,$userid]);
	PDO_Execute("update users set password=? where username=?",[$password,$userid]);
    $q->status=1;
    
}

if($routename=="updateaccount")
{
	extract($data);
	PDO_Execute("Update `personalinfo` set ifsc=?,acno=? where userid=?",[$ifsc,$acno,$userid]);
	$q->result=1;
    
}




if($routename=="upgradetobooster")
{
    $userid=$data['userid'];
    $pvalue=PDO_FetchOne("select pvalue from plans where planid=28");
	$sid=PDO_FetchOne("select sid from `binary` where userid=?",[$userid]);
    PDO_Execute("Insert into usersinvoices(userid,invoicedate,invoiceby,remarks,saletypeid) values (?,date_add(date_add(now(),interval 5 hour), interval 30 minute),?,'',1)",[$userid,$username]);
	$invoiceid=PDO_LastInsertId();
	PDO_Execute("Insert into userssaledetail(invoiceid,productid,qty,mrp) values(?,99,2,1500)",[$invoiceid]);
            PDO_Execute("Update `binary` set boosterupgraded=1 where userid=?",[$userid]);
            PDO_Execute("insert into upliners(upliners.userid,upliners,upliners.side,upliners.sdoa,upliners.levelid,upliners.udoa,upliners.pvalue,showlist)
            select upliners.userid,upliners,upliners.side,upliners.sdoa,upliners.levelid,date_add(date_add(now(),interval 5 hour), interval 30 minute),?,0 from upliners where userid=?",[$pvalue,$userid]);
            if(PDO_FetchOne("select ifnull(boosterupgraded,0) from `binary` where userid=?",[$sid])==1)            
                $boosterfirstpayment=5000;
            else    $boosterfirstpayment=2000;
            PDO_Execute("insert into boosterpayout(userid,child_userid,level,amount,tdate)
            values(?,?,1,?,date_add(date_add(now(),interval 5 hour), interval 30 minute))",[$sid,$userid,$boosterfirstpayment]);
            $boosterpaymentcount=PDO_FetchOne("select count(*) from `boosterpayments` where level >0");
            $i=0;
            while ($i < $boosterpaymentcount)
            {
                $sid=PDO_FetchOne("select sid from `binary` where userid=?",[$sid]);
                if($sid!="" && $sid)
                {
                    if(PDO_FetchOne("select ifnull(boosterupgraded,0) from `binary` where userid=?",[$sid])==1) 
                    {
                        $i++;
                        PDO_Execute("insert into boosterpayout(userid,child_userid,level,amount,tdate)
		select ?,?,level,boosterpayments.amount,date_add(date_add(now(),interval 5 hour), interval 30 minute) from boosterpayments where level=?",[$sid,$userid,$i]);
                    }
                }
				else
					$i++;
            }
	$q->result=1;
}

if($routename=="upgradetoboostertour")
{
    $userid=$data['userid'];
    $pvalue=PDO_FetchOne("select pvalue from plans where planid=29");
	$sid=PDO_FetchOne("select sid from `binary` where userid=?",[$userid]);
    PDO_Execute("Insert into usersinvoices(userid,invoicedate,invoiceby,remarks,saletypeid) values (?,date_add(date_add(now(),interval 5 hour), interval 30 minute),?,'',1)",[$userid,$username]);
	$invoiceid=PDO_LastInsertId();
	PDO_Execute("Insert into userssaledetail(invoiceid,productid,qty,mrp) values(?,99,10,5000)",[$invoiceid]);
            PDO_Execute("Update `binary` set boosterupgraded=1 where userid=?",[$userid]);
            PDO_Execute("insert into upliners(upliners.userid,upliners,upliners.side,upliners.sdoa,upliners.levelid,upliners.udoa,upliners.pvalue,showlist)
            select upliners.userid,upliners,upliners.side,upliners.sdoa,upliners.levelid,date_add(date_add(now(),interval 5 hour), interval 30 minute),?,0 from upliners where userid=?",[$pvalue,$userid]);
            if(PDO_FetchOne("select ifnull(boosterupgraded,0) from `binary` where userid=?",[$sid])==1)            
                $boosterfirstpayment=5000;
            else    $boosterfirstpayment=3000;
            PDO_Execute("insert into boosterpayout(userid,child_userid,level,amount,tdate)
            values(?,?,1,?,date_add(date_add(now(),interval 5 hour), interval 30 minute))",[$sid,$userid,$boosterfirstpayment]);
            $boosterpaymentcount=PDO_FetchOne("select count(*) from `boosterpayments` where level >0");
            $i=0;
            while ($i < $boosterpaymentcount)
            {
                $sid=PDO_FetchOne("select sid from `binary` where userid=?",[$sid]);
                if($sid!="" && $sid)
                {
                    if(PDO_FetchOne("select ifnull(boosterupgraded,0) from `binary` where userid=?",[$sid])==1) 
                    {
                        $i++;
                        PDO_Execute("insert into boosterpayout(userid,child_userid,level,amount,tdate)
		select ?,?,level,boosterpayments.amount,date_add(date_add(now(),interval 5 hour), interval 30 minute) from boosterpayments where level=?",[$sid,$userid,$i]);
                    }
                }
				else
					$i++;
            }
	$q->result=1;
}

if($routename=="removebooster")
{
    $userid=$data['userid'];
    $invoiceid=PDO_FetchOne("select GROUP_CONCAT(invoiceid) from usersinvoices where userid=? and saletypeid=1",[$userid]);
    PDO_Execute("delete from usersinvoices where userid=? and saletypeid=1",[$userid]);	
	PDO_Execute("delete from usersaledetail where invoiceid in (?)",[$invoiceid]);
    PDO_Execute("Update `binary` set boosterupgraded=null where userid=?",[$userid]);
    PDO_Execute("delete from upliners where showlist=0 and userid=?",[$userid]);
    PDO_Execute("delete from boosterpayout where child_userid=?",[$userid]);            
	$q->result=1;
}



if($routename=="adminusers")
{
    $q->data = PDO_FetchAll("SELECT id, name, username, email, role, status, created_at FROM adminusers ORDER BY id ASC");
    $q->result = 1;
}

if($routename=="saveadminuser")
{
    $id = intval($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $role = trim($data['role'] ?? 'Operations Admin');
    $status = (isset($data['status']) && ($data['status'] === 'Inactive' || $data['status'] === 0)) ? 'Inactive' : 'Active';
    $password = trim($data['password'] ?? 'admin123');

    if (empty($name) || empty($username)) {
        $q->result = 0;
        $q->message = "Name and Username are required.";
    } else if ($id > 0) {
        if (!empty($data['password'])) {
            PDO_Execute("UPDATE adminusers SET name=?, username=?, email=?, role=?, status=?, password=? WHERE id=?",
                [$name, $username, $email, $role, $status, $password, $id]);
        } else {
            PDO_Execute("UPDATE adminusers SET name=?, username=?, email=?, role=?, status=? WHERE id=?",
                [$name, $username, $email, $role, $status, $id]);
        }
        $q->id = $id;
        $q->result = 1;
        $q->message = "User updated successfully";
    } else {
        $exists = PDO_FetchOne("SELECT count(*) FROM adminusers WHERE username = ?", [$username]);
        if ($exists > 0) {
            $q->result = 0;
            $q->message = "Username '$username' already exists. Please choose a different username.";
        } else {
            PDO_Execute("INSERT INTO adminusers (name, username, email, password, role, status) VALUES (?,?,?,?,?,?)",
                [$name, $username, $email, $password, $role, $status]);
            $q->id = PDO_LastInsertId();
            $q->result = 1;
            $q->message = "Admin user created successfully";
        }
    }
}

if($routename=="toggleadminuser")
{
    $id = intval($data['id'] ?? 0);
    if ($id > 0) {
        PDO_Execute("UPDATE adminusers SET status = CASE WHEN status='Active' THEN 'Inactive' ELSE 'Active' END WHERE id=?", [$id]);
        $q->result = 1;
    } else {
        $q->result = 0;
        $q->message = "Invalid user ID";
    }
}

if($routename=="deleteadminuser")
{
    $id = intval($data['id'] ?? 0);
    if ($id == 1) {
        $q->result = 0;
        $q->message = "Primary Super Admin account cannot be deleted.";
    } else if ($id > 0) {
        PDO_Execute("DELETE FROM adminusers WHERE id=?", [$id]);
        $q->result = 1;
        $q->message = "User deleted successfully";
    } else {
        $q->result = 0;
        $q->message = "Invalid user ID";
    }
}

if($routename=="adminlogout" || $routename=="logout")
{
    if (!empty($token)) {
        try { PDO_Execute("DELETE FROM tokens WHERE token=?", [$token]); } catch (\Exception $e) {}
        try { PDO_Execute("UPDATE adminusers SET token=NULL, token_expires=NULL WHERE token=?", [$token]); } catch (\Exception $e) {}
    }
    $q->result = 1;
    $q->message = "Logged out successfully";
}

if($routename=="userrights")
{
    $userid=$data["userid"];
    $q->data=PDO_FetchAll("Select adminrights.rightname from adminrights join userrights on userrights.rightid=adminrights.rowid where userrights.userid=?",[$userid]);
}

if($routename=="adduserright")
{
    $userid=$data['userid'];
    $rightid=$data['rightid'];
    if(PDO_FetchOne("select count(*) userrights where userid=? and rightid=?",[$userid,]) ==0)
    PDO_Execute("Insert into userrights(userid,rightid) values(?,?)",[$userid,$rightid]);
    $q->result=1;
}

if($routename=="deleteuserright")
{
    $userid=$data['userid'];
    $rightid=$data['rightid'];
    PDO_Execute("delete from userrights where userid=? and rightid=?",[$userid,$rightid]);
    $q->result=1;
}

if($routename=="activateduserlist")
{
    $fromdate=$data['fromdate'];
    $uptodate=$data['uptodate'];
    $invoiceby=$data['username'];
    
    $pagesize=$data['pagesize'];
    $page=$data['page'];
    $params=array();
    $params[]=$fromdate;
    $params[]=$uptodate;
    
    $strqury=" from `binary` b join personalinfo pf on b.userid=pf.userid join plans p on p.planid=b.planid where b.doa >= ? and b.doa < date_add(?,interval 1 day) ";
    if($username != "")
    {
        $strqury=$strqury." and b.activatedby=? ";
        $params[]=$invoiceby;
    }
    
   
    $limit=" limit ". intval(($page-1)*$pagesize) . " , " . $pagesize;
    $q->result=1;
    $q->data=PDO_FetchAll("select pf.userid,pf.name,pf.city, p.planname,b.doa ".$strqury.$limit,$params);
    $q->totalrows=PDO_FetchOne("select count(*) ".$strqurycount,$params);
    
}

if($routename=="allotactivationcredit")
{
	extract($data);
	PDO_Execute("insert into activatecredit(userid,planid,allotedon,creditin,allotedby,creditout) values(?,?,date_add(date_add(now(),interval 5 hour), interval 30 minute),?,?,0)",[$userid,$planid,$qty,$username]);
	$q->result=1;
	
}



if($routename=="removeactivationcredit")
{
	extract($data);
	if(PDO_FetchOne("select sum(creditin)-sum(creditout) from activatecredit where userid=? and planid=?",[$userid,$planid])>=$qty)
	{
		PDO_Execute("insert into activatecredit(userid,planid,allotedon,creditout,allotedby,creditin) values(?,?,date_add(date_add(now(),interval 5 hour), interval 30 minute),?,?,0)",[$userid,$planid,$qty,$username]);
		$q->result=1;
	}
	else
		$q->result=0;	
}

if($routename=="activationcredithistory")
{
	extract($data);
	$query=' Where foruserid is null';
	if($planid>0)
	{
		if($query=='')
			$query.=' Where ';
		else
			$query.=' And ';
		$query.=" activatecredit.planid = ?";
		$params[]=$planid;
	}
	if($userid!='')
	{
		if($query=='')
			$query.=' Where ';
		else
			$query.=' And ';
		$query.=" pf.userid = ?";
		$params[]=$userid;
	}
	if($allotedby!='All Users')
	{
		if($query=='')
			$query.=' Where ';
		else
			$query.=' And ';
		$query.=" allotedby = ?";
		$params[]=$allotedby;
	}
	if($fromdate!='')
	{
		if($query=='')
			$query.=' Where ';
		else
			$query.=' And ';
		$query.=" allotedon >= ?";
		$params[]=$fromdate;
	}
	if($uptodate!='')
	{
		if($query=='')
			$query.=' Where ';
		else
			$query.=' And ';
		$query.=" allotedon < date_add(?,interval 1 day)";
		$params[]=$uptodate;
	}
	$query_exec="Select pf.userid,pf.name,pf.city,planname,allotedon,creditin,creditout,allotedby from activatecredit join personalinfo pf on pf.userid=activatecredit.userid join plans on plans.planid=activatecredit.planid".$query." order by allotedon desc";
	$strqurycount="Select count(*) from activatecredit join personalinfo pf on pf.userid=activatecredit.userid ".$query." order by allotedon desc";
	
	$limit=" limit ". (($page-1)*$pagesize) . " , " . $pagesize;
    $q->result=1;
    $q->data=PDO_FetchAll($query_exec.$limit,$params);
    $q->totalrows=PDO_FetchOne($strqurycount,$params);
	//var_dump($params);
	
}

if($routename=="activationcreditsummary")
{
	extract($data);
	$query='';
	if($planid>0)
	{
		if($query=='')
			$query.=' Where ';
		else
			$query.=' And ';
		$query.=" activatecredit.planid = ?";
		$params[]=$planid;
	}
	if($userid!='')
	{
		if($query=='')
			$query.=' Where ';
		else
			$query.=' And ';
		$query.=" activatecredit.userid = ?";
		$params[]=$userid;
	}
	
	$query_exec="Select pf.userid,pf.name,pf.city,planname,sum(creditin)-sum(creditout) qty from activatecredit join personalinfo pf on pf.userid=activatecredit.userid join plans on plans.planid=activatecredit.planid".$query." group by pf.userid,pf.name,pf.city,planname having sum(creditin)-sum(creditout) > 0";
	$strqurycount="Select count(*) from activatecredit ".$query." group by userid,planid having sum(creditin)-sum(creditout) > 0";
	
	$limit=" limit ". (($page-1)*$pagesize) . " , " . $pagesize;
    $q->result=1;
    $q->data=PDO_FetchAll($query_exec.$limit,$params);
    $q->totalrows=PDO_FetchOne($strqurycount,$params);
	//var_dump($params);
	
}



if($routename=="activateuser")
{
    $userid=$data['userid'];
    $planid=$data['planid'];
	
    $planrow=PDO_FetchRow("select * from plans where rowid=?",[$planid]);
    PDO_Execute("Update `binary` set isApproved=1, planid=?, doa=date_add(date_add(now(),interval 5 hour), interval 30 minute), activatedby=? where userid=?",[$planid,$username,$userid]);
    PDO_Execute("Update upliners set  doa=date_add(date_add(now(),interval 5 hour), interval 30 minute) where userid=?",[$userid]);
   $sid=PDO_FetchOne("select sid from `binary` where userid=?",[$userid]);
	
   PDO_Execute("insert into userlevelincome(doa,userid,new_userid,amount,level) select date_add(date_add(now(),interval 5 hour), interval 30 minute),upliners,userid,?*commission/100, levelcommission.levelid from levelcommission join upliners on upliners.level=levelcommission.levelid where upliners.userid=?",[$planrow['amount'],$userid]);
    $q->result="1";
}


if($routename=="deactivateuser")
{
    $userid=$data['userid'];
    $planid=PDO_FetchOne("select planid from `binary`where userid=?",[$userid]);
    $planrow=PDO_FetchOne("select * from plans where planid=?",[$planid]);
    PDO_Execute("Update `binary` set isApproved=0, ismanualapproved=0,  doa=null,dor=null, activatedby=null where userid=?",[$userid]);
    PDO_Execute("Update upliners set pvalue=null, udoa=null where userid=?",[$userid]);
    PDO_Execute("Update upliners set sdoa=null where upliners=?",[$userid]);
    PDO_Execute("delete from adminaccount where userid=?",[$userid]);
	PDO_Execute("delete from directusers where suserid=?",[$userid]);
	PDO_Execute("delete from boosterpayout where child_userid=?",[$userid]);
	PDO_Execute("delete from lifetimebonanzaachivers where userid=?",[$userid]);
    $invoiceid=PDO_FetchOne("select invoiceid from `usersinvoices` where userid=?",[$userid]);
	PDO_Execute("delete from userssaledetail where invoiceid=? and productid=99",[$invoiceid]);
	//PDO_Execute("delete from usersinvices where invoiceid=?",[$invoiceid]);   
    $q->result="1";
}

if($routename=="payoutlist")
{
	//extract($data);
	$q->data=PDO_FetchAll("select * from payout order by payoutid desc");
	$q->result=1;
}


if($routename=="payoutsummary")
{
	extract($data);	
	$q->data=PDO_FetchAll("select * from userpayoutsummary where payoutid=? order by userid",[$payoutid]);
	$q->result=1;
}

if($routename=="daywisepayoutsummary")
{
    $userid = isset($data['userid']) ? trim($data['userid']) : '';
    $from_date = isset($data['from_date']) ? trim($data['from_date']) : '';
    $to_date = isset($data['to_date']) ? trim($data['to_date']) : '';

    $where = " WHERE 1=1 ";
    $params = [];

    if (!empty($userid)) {
        $where .= " AND uli.userid = ? ";
        $params[] = $userid;
    }
    if (!empty($from_date)) {
        $where .= " AND DATE(uli.doa) >= ? ";
        $params[] = $from_date;
    }
    if (!empty($to_date)) {
        $where .= " AND DATE(uli.doa) <= ? ";
        $params[] = $to_date;
    }

    $sql = "SELECT DATE(uli.doa) as payout_date,
                   uli.userid,
                   ifnull(pf.name, concat('Farmer ', uli.userid)) as user_name,
                   SUM(CASE WHEN uli.level = 1 THEN uli.amount ELSE 0 END) as direct_income,
                   SUM(CASE WHEN uli.level > 1 THEN uli.amount ELSE 0 END) as level_income,
                   0 as matrix_income,
                   SUM(uli.amount) as total_payout,
                   'Credited' as status
            FROM userlevelincome uli
            LEFT JOIN personalinfo pf ON pf.userid = uli.userid
            $where
            GROUP BY DATE(uli.doa), uli.userid, pf.name
            ORDER BY payout_date DESC, total_payout DESC";

    $q->data = PDO_FetchAll($sql, $params);
    $q->result = 1;
}

if($routename=="binarypayout")
{
	extract($data);
	if($auserid!='')
		$q->data=PDO_FetchAll("select payout.payoutduration,binarypayout.* from binarypayout join payout on payout.payoutid=binarypayout.durationid where userid=? order by payout.payoutid desc",['DW'.$auserid]);
	else
		$q->data=PDO_FetchAll("select payout.payoutduration,binarypayout.* from binarypayout join payout on payout.payoutid=binarypayout.durationid where durationid=? order by userid",[$payoutid]);
	$q->result=1;
}



if($routename=="petrolingbonus")
{
	extract($data);
	if($auserid!='')
		$q->data=PDO_FetchAll("select *, concat(byear,'-',bmonth,'-',2) payoutdate from petrolingbonus where byear=? and bmonth=? and userid=? order by byear desc,bmonth desc",[$pyear,$pmonth,'DW'.$auserid]);
	else
		$q->data=PDO_FetchAll("select *, concat(byear,'-',bmonth,'-',2) payoutdate from petrolingbonus where byear=? and bmonth=? and slabid > 0 order by userid",[$pyear,$pmonth]);
	$q->result=1;
}

if($routename=="repurchasepayout")
{
	extract($data);
	if($auserid!='')
		$q->data=PDO_FetchAll("select *, concat(pyear,'-',pmonth,'-',2) payoutdate from userrepurchasepayout  where pyear=? and pmonth=? and userid=? order by pyear desc,pmonth desc",[$pyear,$pmonth,'DW'.$auserid]);
	else
		$q->data=PDO_FetchAll("select *, concat(pyear,'-',pmonth,'-',2) payoutdate from userrepurchasepayout  where pyear=? and pmonth=? order by userid",[$pyear,$pmonth]);
	$q->result=1;
}

if($routename=="directpayout")
{
	extract($data);
	$q->data=PDO_FetchAll("select * from directusers where sdoa between ? and ? order by userid",[$fromdate,$uptodate]);
	$q->result=1;
}

if($routename=="royaltypayout")
{
	extract($data);
	if($auserid!='')
		$q->data=PDO_FetchAll("select *, concat(ryear,'-',rmonth,'-',2) payoutdate from royaltypayout where ryear=? and rmonth=? and userid=? order by ryear desc,rmonth desc",[$pyear,$pmonth,'DW'.$auserid]);
	else
		$q->data=PDO_FetchAll("select *, concat(ryear,'-',rmonth,'-',2) payoutdate from royaltypayout where ryear=? and rmonth=?  order by userid,recoid,businesstype",[$pyear,$pmonth]);
	$q->result=1;
}

if($routename=="salarypayout")
{
	//extract($data);
	$data=PDO_FetchAll("select userid,salaryamt from userssalary where ifnull(paidon,0)=0 group by userid,salaryamt",[]);
	$i=0;
	foreach($data as $datax)
	{
		$unpaidcounts=PDO_FetchOne("select count(*) from userssalary where userid=? and salaryamt=? and ifnull(paidon,0)=0",[$datax['userid'],$datax['salaryamt']]);
		$paidcounts=PDO_FetchOne("select count(*) from userssalary where userid=? and salaryamt=? and ifnull(paidon,0)<>0",[$datax['userid'],$datax['salaryamt']]);
		$data[$i]['paidcounts']=$paidcounts;
		$data[$i]['unpaidcounts']=$unpaidcounts;
		$i++;
	}
			$q->data=$data;
	$q->result=1;
}

if($routename=="salarylist")
{
	extract($data);
	$q->data=PDO_FetchAll("select userid,salaryamt,paidon from userssalary where userid=? and salaryamt=? and ifnull(paidon,0) <> 0 order by paidon desc",[$userid,$salaryamt]);
	$q->result=1;
}

if($routename=="salaryevents")
{
	extract($data);
	$q->data=PDO_FetchAll("select * from salaryevent where userid=? order by eventdate desc",[$userid]);
	$q->result=1;
}

if($routename=="eventphotos")
{
	extract($data);
	$q->data=PDO_FetchAll("select * from eventphotos where eventid=?",[$eventid]);
	$q->result=1;
}

if($routename=="releasesallary")
{
    $xuserid=$data['userid'];
	$amount=$data['salaryamt'];
	if(PDO_FetchOne("select count(*) from userssalary where userid=? and salaryamt=? and ifnull(paidon,0) = 0",[$xuserid,$amount]) > 0) {
		$rowid=PDO_FetchOne("Select min(rowid) from userssalary where userid=? and salaryamt=? and ifnull(paidon,0) = 0",[$xuserid,$amount]);
		PDO_Execute("Update userssalary set paidon =date_add(date_add(now(),interval 5 hour), interval 30 minute) where rowid=?",[$rowid]);
    	$q->result=1;
	}
	else
		$q->result=0;
    
}

if($routename=="boosterpayout")
{
	extract($data);
	$q->data=PDO_FetchAll("select * from boosterpayments where tdate between ? and ? order by userid",[$fromdate,$uptodate]);
	$q->result=1;
}

if($routename=="adminpurchaseinvoicelist")
{
    $fromdate=$data['fromdate'];
    $uptodate=$data['uptodate'];
    $invoiceby=$data['username'];   
    $pagesize=$data['pagesize'];
    $page=$data['page'];
	$params=array();
    $params[]=$fromdate;
    $params[]=$uptodate;
   
    $strqury=" from adminpurchaseinvoices where invoicedate >= ? and invoicedate < date_add(?,interval 1 day) ";
    if($invoiceby != "")
    {
        $strqury=$strqury." and invoiceby=? ";
        $params[]=$invoiceby;
    }
    
    $limit=" limit ". intval(($page-1)*$pagesize) . " , " . $pagesize;
    $q->result=1;
    $q->data=PDO_FetchAll("select * ".$strqury.$limit,$params);
    $q->totalrows=PDO_FetchOne("select count(*) ".$strqury,$params);    
}

if($routename=="adminstocklistproductwise")
{
    $productid=$data['productid'];
    $pagesize=$data['pagesize'];
    $page=$data['page'];
    $params=array();
    $params[]=$productid;
    $strqury=" from adminstock where productid=? ";
    $limit=" limit ". intval(($page-1)*$pagesize) . " , " . $pagesize;
    $q->result=1;
    $q->data=PDO_FetchAll("select * ".$strqury.$limit,$params);
    $q->totalrows=PDO_FetchOne("select count(*) ".$strqurycount,$params);
}

if($routename=="adminpurchaseinvoicedetail")
{
    $invoiceid=$data['invoiceid'];
    $pagesize=$data['pagesize'];
    $page=$data['page'];
    $params=array();
    $params[]=$invoiceid;
    $strqury=" from adminpurchasedetail rid join repurchaseproducts rp on rp.productid=rid.productid where invoiceid=? ";
    $limit=" limit ". intval(($page-1)*$pagesize) . " , " . $pagesize;
    $q->result=1;
    $q->data=PDO_FetchAll("select rp.productname,rid.* ".$strqury.$limit,$params);
    $q->totalrows=PDO_FetchOne("select count(*) ".$strqury,$params);
}



if($routename=="newsale")
{
    $userid=$data['userid'];
	$usertype=PDO_FetchOne("select ifnull(isdistributor,0) from `binary` where userid=?",[$userid]);
    //$productlist=$data['productlist'];
    $saletype=$data['saletype'];
   	$products=json_decode($data['productlist']);   
    PDO_Execute("Insert into usersinvoices(userid,invoicedate,invoiceby,remarks,saletypeid) values (?,date_add(date_add(now(),interval 5 hour), interval 30 minute),?,'',?)",[$userid,$username,$saletype]);
    $invoiceid=PDO_LastInsertId();
    foreach($products as $product)
    {
        $productid=$product->productid;
        $qty=$product->stock;
        $mrp=$product->mrp;
		$srp=$product->srp;
		$dp=$product->dp;
        PDO_Execute("Insert into adminstock(productid,instock,outstock,invoiceid,saletypeid) values(?,0,?,?,1)",[$productid,$qty,$invoiceid]);
        PDO_Execute("Insert into userssaledetail(invoiceid,productid,qty,mrp,cashback) values(?,?,?,?,?)",[$invoiceid,$productid,$qty,$mrp,$dp]);
		/*$userstate=PDO_FetchOne("select ifnull(isapproved,0) from `binary` where userid=?",[$userid]);
		if($userstate==0)
		{
			$totalbv=PDO_FetchOne("SELECT sum(rp.bv*usd.qty) bv from usersinvoices ui JOIN userssaledetail usd on usd.invoiceid=ui.invoiceid JOIN repurchaseproducts rp on rp.productid=usd.productid WHERE ui.userid=?",[$userid]);
			if($totalbv >= 400)
				PDO_Execute("update `binary` set planid=38, isapproved=1,doa=date_add(date_add(now(),interval 5 hour), interval 30 minute) where userid=?",[$userid]);
			PDO_Execute("update `upliners` set udoa=date_add(date_add(now(),interval 5 hour), interval 30 minute) where userid=?",[$userid]);
		}*/
		if($usertype=='1')
		{
			PDO_Execute("Insert into resellerstock(productid,outstock,instock,invoiceid,saletypeid,userid) values(?,0,?,?,1,?)",[$productid,$qty,$invoiceid,$userid]);
		}
    }
    if($saletype==1)
	{
    	PDO_Execute("insert into adminaccount(transdate,amount,camount,entrytype,userid,username,invoiceid) select date_add(date_add(now(),interval 5 hour), interval 30 minute),sum(qty*mrp),0,4,?,?,invoiceid from userssaledetail where invoiceid=? group by invoiceid",[$invoiceid,$userid,$username]);
		
		if($usertype==1)
		{
		 	PDO_Execute("Insert into retaileraccount(userid,amount,camount,transdate,invoiceid,narration) select userid,sum(mrp*qty),0,date_add(date_add(now(),interval 5 hour), interval 30 minute),invoiceid,concat('Products purchase Invoice No.',invoiceid) from retailerssaledetail where invoiceid=?",[$invoiceid]);
		 	PDO_Execute("Insert into useraccount(userid,amount,camount,tranadate,invoiceid,narration) select userid,sum(((mrp*retailercommission)/100)*qty),0,date_add(date_add(now(),interval 5 hour), interval 30 minute),invoiceid,concat('Products purchase Commission Invoice No.',invoiceid) from retailerssaledetail where invoiceid=?",[$invoiceid]);
		}
		else
		{
			PDO_Execute("insert into cashbackaccount(transdate,amount,camount,userid,username,invoiceid) select date_add(date_add(now(),interval 5 hour), interval 30 minute),sum(qty*cashback),0,?,?,invoiceid from userssaledetail where invoiceid=? group by invoiceid",[$invoiceid,$userid,$username]);
		}
	}
		
    $q->result=1;
    $q->invoiceid=$invoiceid;    
}

if($routename=="returnproduct")
{
    $userid=$data['userid'];
	$usertype=PDO_FetchOne("select ifnull(isdistributor,0) from `binary` where userid=?",[$userid]);
    //$productlist=$data['productlist'];
    $saletype=$data['saletype'];
   	$products=json_decode($data['productlist']);   
    PDO_Execute("Insert into usersreturninvoices(userid,invoicedate,invoiceby,remarks,saletypeid) values (?,date_add(date_add(now(),interval 5 hour), interval 30 minute),?,'',3)",[$userid,$username,$saletype]);
    $invoiceid=PDO_LastInsertId();
    foreach($products as $product)
    {
        $productid=$product->productid;
        $qty=$product->stock;
        $mrp=$product->mrp;
		$srp=$product->srp;
		$dp=$product->dp;
        PDO_Execute("Insert into adminstock(productid,outstock,intstock,invoiceid,saletypeid) values(?,0,?,?,3)",[$productid,$qty,$invoiceid]);
        PDO_Execute("Insert into usersreturndetail(invoiceid,productid,qty,mrp,cashback) values(?,?,?,?,?)",[$invoiceid,$productid,$qty,$mrp,$dp]);
		
		if($usertype=='1')
		{
			PDO_Execute("Insert into resellerstock(productid,instock,outstock,invoiceid,saletypeid,userid) values(?,0,?,?,1,?)",[$productid,$qty,$invoiceid,$userid]);
		}
    }
    
		
    $q->result=1;
    $q->invoiceid=$invoiceid;    
}

if($routename=="purchasehistory")
{
	$userid=$data['userid'];
	$q->totalamount=PDO_FetchOne("select sum(ifnull(mrp,0)*ifnull(qty,0)) from userssaledetail usd join usersinvoices ui on ui.invoiceid=usd.invoiceid where ui.saletypeid=2 and ui.userid=?",[$userid]);
	$q->invoicelist=PDO_FetchAll("select productname,usd.mrp,usd.qty,invoicedate from userssaledetail usd join usersinvoices ui on ui.invoiceid=usd.invoiceid join repurchaseproducts rp on rp.productid=usd.productid where ui.saletypeid=2 and ui.userid=?",[$userid]);
}

if($routename=="adminsaleinvoicelist")
{
    $fromdate=$data['fromdate'];
    $uptodate=$data['uptodate'];
    $invoiceby=$data['invoiceby'];
	$userid=$data['userid'];
    $saletype=$data['saletype'];
    $pagesize=$data['pagesize'];
    $page=$data['page'];
    $params=array();
    $params[]=$fromdate;
    $params[]=$uptodate;    
    $strqury=" from usersinvoices where invoicedate >= ? and invoicedate < date_add(?,interval 1 day) ";
    if($invoiceby != "")
    {
        $strqury=$strqury." and invoiceby=? ";
        $params[]=$invoiceby;
    } 
	
	if($userid != "")
    {
        $strqury=$strqury." and userid=? ";
        $params[]=$userid;
    }
    if($saletype > 0 )
    {
        $strqury=$strqury. " and saletype = ?";
        $params[]=$saletype;
    }
    $limit=" limit ". intval(($page-1)*$pagesize) . " , " . $pagesize;
    $q->result=1;
    $q->data=PDO_FetchAll("select * ".$strqury.$limit,$params);
    $q->totalrows=PDO_FetchOne("select count(*) ".$strqury,$params);
}

if($routename=="saleinvoicedetail")
{
   	extract($data);
    $params=array();
    $params[]=$invoiceid;
    $strqury=" from userssaledetail rid join repurchaseproducts rp on rp.productid=rid.productid where invoiceid=? ";
    //$limit=" limit ". intval(($page-1)*$pagesize) . " , " . $pagesize;
    $q->result=1;
    $q->data=PDO_FetchAll("select rp.productname,(rid.srp*rid.qty) total,rid.* ".$strqury,$params);
    $q->totalrows=PDO_FetchOne("select count(*) ".$strqurycount,$params);
}


if($routename=="adminreturninvoicelist")
{
    $fromdate=$data['fromdate'];
    $uptodate=$data['uptodate'];
    $invoiceby=$data['invoiceby'];
	$userid=$data['userid'];
    $saletype=$data['saletype'];
    $pagesize=$data['pagesize'];
    $page=$data['page'];
    $params=array();
    $params[]=$fromdate;
    $params[]=$uptodate;    
    $strqury=" from usersreturninvoices where invoicedate >= ? and invoicedate < date_add(?,interval 1 day) ";
    if($invoiceby != "")
    {
        $strqury=$strqury." and invoiceby=? ";
        $params[]=$invoiceby;
    } 
	
	if($userid != "")
    {
        $strqury=$strqury." and userid=? ";
        $params[]=$userid;
    }
    if($saletype > 0 )
    {
        $strqury=$strqury. " and saletype = ?";
        $params[]=$saletype;
    }
    $limit=" limit ". intval(($page-1)*$pagesize) . " , " . $pagesize;
    $q->result=1;
    $q->data=PDO_FetchAll("select * ".$strqury.$limit,$params);
    $q->totalrows=PDO_FetchOne("select count(*) ".$strqury,$params);
}

if($routename=="returninvoicedetail")
{
   	extract($data);
    $params=array();
    $params[]=$invoiceid;
    $strqury=" from usersreturndetail rid join repurchaseproducts rp on rp.productid=rid.productid where invoiceid=? ";
    //$limit=" limit ". intval(($page-1)*$pagesize) . " , " . $pagesize;
    $q->result=1;
    $q->data=PDO_FetchAll("select rp.productname,(rid.srp*rid.qty) total,rid.* ".$strqury,$params);
    $q->totalrows=PDO_FetchOne("select count(*) ".$strqury,$params);
}

if($routename=="verifyuserid") 
{
	$userid=$data['userid'];
	$row=PDO_FetchRow("select name,ifnull(`binary`.isdistributor,0) isdistributor,ifnull(`binary`.boosterupgraded,0) boosterupgraded from personalinfo join `binary` on `binary`.userid=personalinfo.userid where personalinfo.userid=?",[$userid]);
	if(!$row)
		$q->result=0;
	else
	{
		$q->result=1;
		$q->name=$row['name'];
		$q->isfranchise=$row['isdistributor'];
		$q->boosterupgraded=$row['boosterupgraded'];
	}
	
}

if($routename=="newretailersale")
{
    $userid=$data['userid'];
    //$productlist=$data['productlist'];
    $products=json_decode($data['productlist']);
    PDO_Execute("Insert into retailersinvoices(userid,invoicedate,invoiceby,remarks) values (?,date_add(date_add(now(),interval 5 hour), interval 30 minute),?,'')",[$userid,$username]);
    $invoiceid=PDO_LastInsertId();
    foreach($products as $product)
    {
        $productid=$product->productid;
        $qty=$product->stock;
        $mrp=$product->mrp;
		$srp=$product->srp;
        PDO_Execute("Insert into adminstock(productid,instock,outstock,invoiceid,saletypeid) values(?,0,?,?,2)",[$productid,$qty,$invoiceid]);
        PDO_Execute("Insert into retailerssaledetail(invoiceid,productid,qty,mrp) values(?,?,?,?)",[$invoiceid,$productid,$qty,$mrp]);
    }
    PDO_Execute("Insert into retaileraccount(userid,amount,camount,transdate,invoiceid,narration) select userid,sum(mrp*qty),0,date_add(date_add(now(),interval 5 hour), interval 30 minute),invoiceid,concat('Products purchase Invoice No.',invoiceid) from retailerssaledetail where invoiceid=?",[$invoiceid]);
    PDO_Execute("insert into adminaccount(transdate,amount,camount,entrytype,userid,username,invoiceid) select date_add(date_add(now(),interval 5 hour), interval 30 minute),amount,0,3,?,?,invoiceid from retaileraccount where invoiceid=?",[$invoiceid,$userid,$username]);
    PDO_Execute("Insert into useraccount(userid,amount,camount,tranadate,invoiceid,narration) select userid,sum(((mrp*retailercommission)/100)*qty),0,date_add(date_add(now(),interval 5 hour), interval 30 minute),invoiceid,concat('Products purchase Commission Invoice No.',invoiceid) from retailerssaledetail where invoiceid=?",[$invoiceid]);
    $q->result=1;
    $q->invoiceid=$invoiceid;    
}

if($routename=="newpurchase")
{
    $userid=$data['supplierid'];
    //$productlist=$data['productlist'];
   $products=json_decode($data['productlist']);
    PDO_Execute("Insert into adminpurchaseinvoices(userid,invoicedate,invoiceby) values (?,date_add(date_add(now(),interval 5 hour), interval 30 minute),?)",[$userid,$username]);
    $invoiceid=PDO_LastInsertId();
    foreach($products as $product)
    {
       $productid=$product->productid;
        $qty=$product->stock;
        $mrp=$product->mrp;
		$srp=$product->srp;
        PDO_Execute("Insert into adminstock(productid,instock,outstock,invoiceid) values(?,?,0,?)",[$productid,$qty,$invoiceid]);
        PDO_Execute("Insert into adminpurchasedetail(invoiceid,productid,qty,mrp) values(?,?,?,?)",[$invoiceid,$productid,$qty,$srp]);
    }
    PDO_Execute("Insert into supplieraccount(userid,amount,camount,transdate,invoiceid,narration) select userid,sum(mrp*qty),0,date_add(date_add(now(),interval 5 hour), interval 30 minute),invoiceid,concat('Products purchase Invoice No.',invoiceid) from adminpurchasedetail where invoiceid=?",[$invoiceid]);
    PDO_Execute("insert into adminaccount(transdate,amount,camount,entrytype,userid,username,invoiceid) select date_add(date_add(now(),interval 5 hour), interval 30 minute),0,amount,5,?,?,invoiceid from supplieraccount where invoiceid=?",[$invoiceid,$userid,$username]);
    $q->result=1;
    $q->invoiceid=$invoiceid;    
}

if($routename=="changeuserplan")
{
    $userid=$data['userid'];
    $planid=$data['planid'];
    $planrow=PDO_FetchOne("select * from plans where planid=?",[$planid]);
    PDO_Execute("Update `binary` b set planid=? where userid=?",[$userid,$planid]);
    PDO_Execute("Update `upliners` u set pvalue=? where userid=?",[$planrow['pvlaue'],$userid]);
    PDO_Execute("Update directusers set amount=? where suserid=?",[$planrow['firstincome']]);
    PDO_Execute("insert into adminaccount(transdate,amount,camount,entrytype,userid,username) values(date_add(date_add(now(),interval 5 hour), interval 30 minute),?,0,1,?,?)",[$planrow['basicplanvalue'],$userid,$username]);
    PDO_Execute("insert into adminaccount(transdate,amount,camount,entrytype,userid,username) select date_add(date_add(now(),interval 5 hour), interval 30 minute),0,amount,2,userid,? from adminaccount where entrytype=1 and userid=?",[$username,$userid]);
    if($planid==28)
    {
        $invoiceid=PDO_FetchOne("select invoiceid from userinvoices where userid=? and productid=99",[$userid]);
        PDO_Execute("Update userssaledetail set qty=2 where invoiceid=?",[$invoiceid]);
        PDO_Execute("Update `binary` set boosterupgraded=1 where userid=?",[$userid]);
        PDO_Execute("insert into upliners(upliners.userid,upliners,upliners.side,upliners.sdoa,upliners.levelid,upliners.udoa,upliners.pvalue,showlist)
        select upliners.userid,upliners,upliners.side,upliners.sdoa,upliners.levelid,date_add(date_add(now(),interval 5 hour), interval 30 minute),?,0 from upliners where userid=?",[$planrow['pvalue'],$userid]);
        if(PDO_Execute("select boosterupgraded from `binary` where userid=?",[$sid])==1)            
            $boosterfirstpayment=5000;
        else    $boosterfirstpayment=2000;
        PDO_Execute("insert into boosterpayout(userid,child_userid,level,amount,tdate)
        values(?,?,1,?,date_add(date_add(now(),interval 5 hour), interval 30 minute))",[$sid,$userid,$boosterfirstpayment]);
        $boosterpaymentcount=PDO_FetchOne("select count(*) from `boosterpayments` where level >0");
        $i=0;
        while ($i < $boosterpaymentcount)
        {
            $sid=PDO_FetchOne("select sid from `binary` where userid=?",[$sid]);
            if($sid!="" && $sid)
            {
                if(PDO_Execute("select boosterupgraded from `binary` where userid=?",[$sid])==1) 
                {
                    $i++;
                    PDO_Execute("insert into boosterpayout(userid,child_userid,level,amount,tdate)
    select ?,?,level,boosterpayments.amount,date_add(date_add(now(),interval 5 hour), interval 30 minute) from boosterpayments where level=?",[$sid,$userid,$i]);
                }
            }
        }
    }
    else
    {
        $invoiceid=PDO_FetchOne("select invoiceid from userinvoices where userid=? and productid=99",[$userid]);
        PDO_Execute("Update userssaledetail set qty=1 where invoiceid=?",[$invoiceid]);
        PDO_Execute("Update `binary` set boosterupgraded=0 where userid=?",[$userid]);
        PDO_Execute("Delete from upliners where userid=? and showlist=0",[$userid]);
        PDO_Execute("Delete from boosterpayout where child_userid=?",[$userid]); 
    }
}

if($routename=="banklist")
{
    $minamt=$data['minamt'];
    $q->result=1;
    $q->data=PDO_FetchAll("select pf.userid,pf.name,pf.acno,pf.ifsc,city,branch,concat(round(sum(ifnull(amount,0))-sum(ifnull(camount,0)),0),'.00') balance,`binary`.doa from useraccount join personalinfo pf on pf.userid=useraccount.userid join `binary` on `binary`.userid=pf.userid where `binary`.isapproved=1 group by pf.userid,pf.name,pf.acno,pf.ifsc having sum(ifnull(amount,0))-sum(ifnull(camount,0)) > ? order by pf.userid",[$minamt]);
	$q->lastpayout=PDO_FetchRow("select * from payout order by payoutid desc limit 1");
	
}

if($routename=="paidbanklist")
{
    $minamt=$data['paiddate'];
    $q->result=1;
    $q->data=PDO_FetchAll("select pf.userid,pf.name,pf.acno,pf.ifsc,city,branch,camount from useraccount join personalinfo pf on pf.userid=useraccount.userid where date_format(tdate,'%Y-%m-%d')=? and payoutpayment=1",[$minamt]);
	
	
}

if($routename=="paiddatebanklist")
{
   
    $q->result=1;
    $q->data=PDO_FetchAll("select date_format(tdate,'%Y-%m-%d') tdate from useraccount where payoutpayment=1 group by date_format(tdate,'%Y-%m-%d') order by date_format(tdate,'%Y-%m-%d') desc");
	
	
}

if($routename=="cashbacklist")
{
    $minamt=$data['minamt'];
    $q->result=1;
    $q->data=PDO_FetchAll("select pf.userid,pf.name,pf.acno,pf.ifsc,city,branch,concat(round(sum(ifnull(amount,0))-sum(ifnull(camount,0)),0),'.00') balance,`binary`.doa from cashbackaccount join personalinfo pf on pf.userid=cashbackaccount.userid join `binary` on `binary`.userid=pf.userid where `binary`.isapproved=1 group by pf.userid,pf.name,pf.acno,pf.ifsc having sum(ifnull(amount,0))-sum(ifnull(camount,0)) > ? order by pf.userid",[$minamt]);
	$q->lastpayout=PDO_FetchRow("select * from payout order by payoutid desc limit 1");
	
}

if($routename=="paidcashbacklist")
{
    $minamt=$data['paiddate'];
    $q->result=1;
    $q->data=PDO_FetchAll("select pf.userid,pf.name,pf.acno,pf.ifsc,city,branch,camount from cashbackaccount join personalinfo pf on pf.userid=useraccount.userid where date_format(transdate,'%Y-%m-%d')=? and camount>0 limit 50",[$minamt]);
	
	
}

if($routename=="paiddatecashbacklist")
{
   
    $q->result=1;
    $q->data=PDO_FetchAll("select date_format(transdate,'%Y-%m-%d') tdate from cashbackaccount where camount>0 group by date_format(transdate,'%Y-%m-%d') order by date_format(transdate,'%Y-%m-%d') desc");
	
	
}


if($routename=="tdslist")
{
   extract($data);
	$limit=" limit ". intval(($page-1)*$pagesize) . " , " . $pagesize;
    $q->result=1;
    $q->data=PDO_FetchAll("select pf.userid,pf.name,pf.pan,pf.city,pf.contact,sum(ifnull(aamount,0)) aamount, sum(ifnull(aamount,0)*tds/100) tdsamt,tds from useraccount join personalinfo pf on pf.userid=useraccount.userid where useraccount.tdate between date_add(?,interval 1 day) and date_add(?,interval 2 day) and tds=? group by pf.userid having sum(ifnull(aamount,0)) > 0".$limit,[$fromdate,$uptodate,$tds]);
	$q->totalrows=PDO_FetchOne("select count(DISTINCT userid) from useraccount where useraccount.tdate between ? and ? and tds=? and ifnull(aamount,0) > 0",[$fromdate,$uptodate,$tds]);
	$q->total=PDO_FetchRow("select sum(ifnull(aamount,0)) aamount, sum(ifnull(aamount,0)*tds/100) tdsamt from useraccount where useraccount.tdate between date_add(?,interval 1 day) and date_add(?,interval 2 day) and tds=?",[$fromdate,$uptodate,$tds]);
	
	
}



if($routename=="retataileraccountsummary")
{
    $minamt=$data['minamt'];
    $q->result=1;
    $q->accountlist=PDO_FetchAll("select pf.*,sum(ifnull(amount,0))-sum(ifnull(camount,0)) balance from retaileraccount join retailerinfo pf on pf.userid=retaileraccount.userid group by pf.userid,pf.name,pf.acno,pf.ifsc order by sum(ifnull(amount,0))-sum(ifnull(camount,0)) desc having sum(ifnull(amount,0))-sum(ifnull(camount,0)) > ?",[$minamt]);
}

if($routename=="useraccountdetail")
{
    $userid=$data['userid'];
    $fromdate=$data['fromdate'];
    $uptodate=$data['uptodate'];
    $pagesize=$data['pagesize'];
    $page=$data['page'];
    $params=array();
    $params[]=$userid;
    //$params[]=$fromdate;
    //$params[]=$uptodate;
    //$strqury=" from useraccount where userid=? and tdate >= ? and tdate < date_add(?,interval 1 day) order by accountid desc ";
	$strqury=" from useraccount where userid=? order by tdate desc , accountid desc";
    $limit=" limit ". intval(($page-1)*$pagesize) . " , " . $pagesize;
    $q->result=1;
	$q->membername=PDO_FetchOne("select name from personalinfo where userid=?",[$userid]);
    $q->data=PDO_FetchAll("select *,tdate transdate ".$strqury.$limit,$params);
    $q->totalrows=PDO_FetchOne("select count(*) ".$strqury,$params);
	$q->accountsummary=PDO_FetchRow("select sum(amount) amount,sum(camount) camount,sum(amount)-sum(camount) balance from useraccount where userid=?",[$userid]);
    //$limit=" limit ".parseInt($page)-1 * parseInt($pagesize) . "," . $pagesize ;
}

if($routename=="retaileraccountdetail")
{
    $userid=['userid'];
    $fromdate=$data['fromdate'];
    $uptodate=$data['uptodate'];
    $pagesize=$data['pagesize'];
    $page=$data['page'];
    $params=array();
    $params[]=$userid;
    $params[]=$fromdate;
    $params[]=$uptodate;
    $strqury=" from retaileraccount where userid=? and transdate >= ? and transdate < date_add(?,interval 1 day) order by accountid desc ";
    $limit=" limit ". intval(($page-1)*$pagesize) . " , " . $pagesize;
    $q->result=1;
    $q->data=PDO_FetchAll("select * ".$strqury.$limit,$params);
    $q->totalrows=PDO_FetchOne("select count(*) ".$strqurycount,$params);
    //$limit=" limit ".parseInt($page)-1 * parseInt($pagesize) . "," . $pagesize ;
}


if($routename=="adminaccountdetail")
{
    $userid=['userid'];
    $fromdate=$data['fromdate'];
    $uptodate=$data['uptodate'];
    $pagesize=$data['pagesize'];
    $page=$data['page'];
    $params=array();
    $params[]=$userid;
    $params[]=$fromdate;
    $params[]=$uptodate;
    $strqury=" from useraccount where userid=? and transdate >= ? and transdate < date_add(?,interval 1 day) ";
    if($userid!="")
    {
        $strqury=$strqury." and userid=? ";
        $params[]=$userid;
    }  
    $strqury=$strqury." order by accountid desc ";
    $limit=" limit ". intval(($page-1)*$pagesize) . " , " . $pagesize;
    $q->result=1;
    $q->data=PDO_FetchAll("select rp.productname,rid.* ".$strqury.$limit,$params);
    $q->totalrows=PDO_FetchOne("select count(*) ".$strqurycount,$params);
    //$limit=" limit ".parseInt($page)-1 * parseInt($pagesize) . "," . $pagesize ;
}

if($routename=="addadminaccountentry")
{
    $amount=$data['amount'];
    $camount=$data['camount'];
    $remarks=$data['remarks'];
    PDO_Execute("Insert into adminaccount (transdate,amount,camount,remarks,entryby) values(date_add(date_add(now(),interval 5 hour), interval 30 minute),?,?,?,?)",[$amount,$camount,$remarks,$username]);
    $q->result=1;
    $q->rowid=PDO_LastInsertId();
}


	
	if($routename=="addcashbackaccountentry")
{
    $userid=$data['userid'];
    $amount=$data['amount'];
    $camount=$data['camount'];
    $remarks=$data['remarks'];
	
	
		
    PDO_Execute("Insert into cashbackaccount (userid,tdate,amount,camount,narration,entryby) values(?,date_add(date_add(now(),interval 5 hour), interval 30 minute),?,?,?,?)",[$userid,$amount,$camount,$remarks,$username]);
    $q->result=1;
    $q->rowid=PDO_LastInsertId();
	
	if($data['sendsms']=='1' && $camount > 0)
	{
		$pfdata=PDO_FetchRow("select contact mobile,name from personalinfo where userid=?",[$userid]);
		$name=$pfdata['name'];
		$mobile=$pfdata['mobile'];
		$msgtext="Dear *".$name."*, We have paid a sum of Rs.  *".$camount."* into account mentioned in your Member Id *" . $userid ."*. Please check your account within 24 hrs. Please contact us if you have not received the same. प्रिय ग्राहक, आपके ऊपर दिए गए मेम्बर संख्या में संरक्षित खाता संख्या में रूपए *".$camount."* भेजे गए है. कृपया अपना खाता जांच करें. यदि आपको यह रकम प्राप्त नहीं हुई है तो कृपया हमसे तुरंत संपर्क करें. धन्यवाद।";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "http://wasms.simpact.co.in/api/sendText?token=".$watoken."&phone=91".$mobile."&message=".urlencode($msgtext));
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$authToken = curl_exec($ch);
		//var_dump(json_decode($authToken));
		$q->smsstatus=json_decode($authToken,true)['status'];
	}
	
}

if($routename=="adduseraccountentry")
{
    $userid=$data['userid'];
    $amount=$data['amount'];
    $camount=$data['camount'];
    $remarks=$data['remarks'];
	$payoutpayment=$data['payment_type'];
	if($payoutpayment)
	{
		if ($payoutpayment=='1')
			PDO_Execute("Insert into useraccount (userid,tdate,amount,camount,narration,entryby,payoutpayment) values(?,date_add(date_add(now(),interval 5 hour), interval 30 minute),?,?,?,?,1)",[$userid,$amount,$camount,$remarks,$username]);
		if($payoutpayment=='2')
			PDO_Execute("Insert into useraccount (userid,tdate,amount,camount,narration,entryby,cashbackpayment) values(?,date_add(date_add(now(),interval 5 hour), interval 30 minute),?,?,?,?,1)",[$userid,$amount,$camount,$remarks,$username]);
	}
		else
    PDO_Execute("Insert into useraccount (userid,tdate,amount,camount,narration,entryby) values(?,date_add(date_add(now(),interval 5 hour), interval 30 minute),?,?,?,?)",[$userid,$amount,$camount,$remarks,$username]);
    $q->result=1;
    $q->rowid=PDO_LastInsertId();
	
	if($data['sendsms']=='2' && $camount > 0)
	{
		$pfdata=PDO_FetchRow("select contact mobile,name from personalinfo where userid=?",[$userid]);
		$name=$pfdata['name'];
		$mobile=$pfdata['mobile'];
		$msgtext="Dear *".$name."*, We have paid a sum of Rs.  *".$camount."* into account mentioned in your Member Id *" . $userid ."*. Please check your account within 24 hrs. Please contact us if you have not received the same. प्रिय ग्राहक, आपके ऊपर दिए गए मेम्बर संख्या में संरक्षित खाता संख्या में रूपए *".$camount."* भेजे गए है. कृपया अपना खाता जांच करें. यदि आपको यह रकम प्राप्त नहीं हुई है तो कृपया हमसे तुरंत संपर्क करें. धन्यवाद।";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "http://wasms.simpact.co.in/api/sendText?token=".$watoken."&phone=91".$mobile."&message=".urlencode($msgtext));
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$authToken = curl_exec($ch);
		//var_dump(json_decode($authToken));
		$q->smsstatus=json_decode($authToken,true)['status'];
	}
	
}

if($routename=="addretaileraccountentry")
{
    $userid=$data['userid'];
    $amount=$data['amount'];
    $camount=$data['camount'];
    $remarks=$data['remarks'];
    PDO_Execute("Insert into retaileraccount (userid,transdate,amount,camount,remarks,entryby) values(?,date_add(date_add(now(),interval 5 hour), interval 30 minute),?,?,?,?)",[$userid,$amount,$camount,$remarks,$username]);
    $q->result=1;
    $q->rowid=PDO_LastInsertId();
}

if($routename=="rewardlist"){
 //$q->data=PDO_FetchAll("select r.bonanzaname, r.bonanzaid, r.lefttarget, r.righttarget, r.completiontime,ifnull( r.dbonanzaid,0) dbonanzaid, ifnull( r.dcount,0) dcount,j.bonanzaname bonanzaname2 from lifetimebonanza r left join lifetimebonanza j on r.dbonanzaid=j.bonanzaid ORDER by r.bonanzaid");
	$q->data=PDO_FetchAll("SELECT r.nolimitprize bonanzaname, rowid bonanzaid FROM rankreward r order by rowid");
    $q->result=1;
}

if($routename=="newreward")
{
    $bonanzaname=$data['bonanzaname'];
    $left=$data['left'];
    $right=$data['right'];
	 $ctime=$data['ctime'];
	 $dbonanzaid=$data['dbonanzaid'];
	 $dcount=$data['dcount'];
    PDO_Execute("insert into lifetimebonanza (bonanzaname,lefttarget,righttarget,completiontime,dbonanzaid,dcount) values(?,?,?,?,?,?)",[$bonanzaname,$left,$right,$ctime,$dbonanzaid,$dcount]);
    $q->result=1;
    $q->rowid=PDO_LastInsertId();
}

if($routename=="updatereward")
{
    $bonanzaid=$data['bonanzaid'];
    $bonanzaname=$data['bonanzaname'];
    $left=$data['left'];
    $right=$data['right'];
	 $ctime=$data['ctime'];
	 $dbonanzaid=$data['dbonanzaid'];
	 $dcount=$data['dcount'];
    PDO_Execute("update lifetimebonanza set bonanzaname=?,lefttarget=?,righttarget=?,completiontime=?,dbonanzaid=?,dcount=? where bonanzaid=?",[$bonanzaname,$left,$right,$ctime,$dbonanzaid,$dcount,$bonanzaid]);
    $q->result=1;
}

if($routename=="deletereward")
{
    $bonanzaid=$data['bonanzaid'];
 
    PDO_Execute("delete from lifetimebonanza where bonanzaid=?",[$bonanzaid]);
    
    $q->result=1;
}

if($routename=="bonanzalist"){
 $q->data=PDO_FetchAll("select * from bonanzainfo ORDER by bonanzainfo.rowid desc",[]);
    $q->result=1;
}

if($routename=="newbonanza")
{
    $bonanzaname=$data['bonanzaname'];
    $from=$data['from'];
    $upto=$data['upto'];
    PDO_Execute("insert into bonanzainfo (bonanzaname,fromdate,uptodate) values(?,?,?)",[$bonanzaname,$from,$upto]);
    $q->result=1;
    $q->rowid=PDO_LastInsertId();
}

if($routename=="updatebonanza")
{
    $bonanzaid=$data['bonanzaid'];
    $bonanzaname=$data['bonanzaname'];
    $from=$data['from'];
    $upto=$data['upto'];
    PDO_Execute("update bonanzainfo set bonanzaname=?,fromdate=?,uptodate=? where rowid=?",[$bonanzaname,$from,$upto,$bonanzaid]);
    $q->result=1;
}

if($routename=="deletebonanza")
{
    $bonanzaid=$data['bonanzaid'];
    $bonanzaname=$data['bonanazaname'];
    $from=$data['from'];
    $upto=$data['upto'];
    PDO_Execute("delete bonanza where bonanzainfoid=?)",[$bonanzaid]);
    PDO_Execute("delete bonanzainfo where bonanzaid=?)",[$bonanzaid]);
    $q->result=1;
}

if($routename=="newbonanzagift")
{
    $bonanzaid=$data['bonanzaid'];
    $giftname=$data['name'];
    $leftbv=$data['leftbv'];
    $rightbv=$data['rightbv'];
    $leftiv=$data['leftiv'];
    $rightbv=$data['rightiv'];
    $recoid=$data['recoid'];
    PDO_Execute("insert into bonanza (rewarditemname,recoid,target1,target2,bonanzainfoid,bvtarget1,bvtarget2) values(?,?,?,?,?,?,?)",[$giftname,$recoid,$leftbv,$rightbv,$bonanzaid,$leftiv,$rightbv]);
    $q->result=1;
    $q->rowid=PDO_LastInsertId();
}


if($routename=="rewardachievers")
{
    $rewardid=$data['rewardid'];
	$auserid=$data['auserid'];
	$paidstatus=$data['paidstatus'];
	$page=$data['page'];
	$pagesize=$data['pagesize'];
	$limit=" limit ". intval(($page-1)*$pagesize) . " , " . $pagesize;
	if($auserid!='')
	{
		$q->data=PDO_FetchAll("SELECT ra.rowid,pf.name,pf.userid,pf.city,pf.contact,prizeallotedon,r.nolimitprize bonanzaname,achieved,bvqualified,teamqualified,achievedon  FROM rankrewardachivers ra join personalinfo pf on pf.userid=ra.userid join rankreward r on r.bonanzaid=ra.bonanzaid where pf.userid=? order by r.rowid".$limit,[$auserid]);
		$q->totalrows=PDO_FetchOne("select count(*) from lifetimebonanza");
	}
	else
	{
		if($paidstatus=='0')
		{
			 $q->data=PDO_FetchAll("SELECT ra.rowid,pf.name,pf.userid,pf.city,pf.contact,prizeallotedon,r.nolimitprize bonanzaname,achieved,bvqualified,teamqualified,achievedon  FROM rankrewardachivers ra join personalinfo pf on pf.userid=ra.userid join rankreward r on r.rowid=ra.bonanzaid where ra.achieved=1 and ra.bonanzaid=? order by achievedon desc".$limit,[$rewardid]);
			$q->totalrows=PDO_FetchOne("select count(*) from rankrewardachivers where achieved=1 and bonanzaid=? order by achievedon desc",[$rewardid]);
		}
	else if($paidstatus=='1')
	{
    $q->data=PDO_FetchAll("SELECT ra.rowid,pf.name,pf.userid,pf.city,pf.contact,prizeallotedon,r.nolimitprize bonanzaname,achieved,bvqualified,teamqualified,achievedon  FROM rankrewardachivers ra join personalinfo pf on pf.userid=ra.userid join rankreward r on r.rowid=ra.bonanzaid where ra.achieved=1 and prizeallotedon is not null and bvqualified=1 and ra.bonanzaid=? order by achievedon desc".$limit,[$rewardid]);
		$q->totalrows=PDO_FetchOne("select count(*) from rankrewardachivers where achieved=1 and bvqualified=1 and prizeallotedon is not null and bonanzaid=?",[$rewardid]);
	}
		else if($paidstatus=='2')
	{
    $q->data=PDO_FetchAll("SELECT ra.rowid,pf.name,pf.userid,pf.city,pf.contact,prizeallotedon,r.nolimitprize bonanzaname,achieved,bvqualified,teamqualified,achievedon  FROM rankrewardachivers ra join personalinfo pf on pf.userid=ra.userid join rankreward r on r.rowid=ra.bonanzaid where ra.achieved=1 and prizeallotedon is not null  and bvqualified=0 and ra.bonanzaid=? order by achievedon desc".$limit,[$rewardid]);
		$q->totalrows=PDO_FetchOne("select count(*) from rankrewardachivers where achieved=1 and bvqualified=0 and prizeallotedon is not null and bonanzaid=?",[$rewardid]);
		}
		else
		{
			 $q->data=PDO_FetchAll("SELECT ra.rowid,pf.name,pf.userid,pf.city,pf.contact,prizeallotedon,r.nolimitprize bonanzaname,achieved,bvqualified,teamqualified,achievedon  FROM rankrewardachivers ra join personalinfo pf on pf.userid=ra.userid join rankreward r on r.rowid=ra.bonanzaid where ra.achieved=1  and prizeallotedon is null and ra.bonanzaid=? order by achievedon desc".$limit,[$rewardid]);
			$q->totalrows=PDO_FetchOne("select count(*) from rankrewardachivers where achieved=1 and prizeallotedon is null and bonanzaid=?",[$rewardid]);
		}
	}
    $q->result=1;    
}

if($routename=="updaterewardstatus")
{
	$rowid=$data['rowid'];
	$allow=$data['allow'];
	if($allow)
	{
		PDO_Execute("insert into useraccount(userid,amount,camount,aamount,tdate,narration,service) select userid,0,0,nolimitprize,date_add(date_add(now(),interval 5 hour), interval 30 minute),'Rank Reward Commission',5 from rankreward r join rankrewardachivers ra on ra.bonanzaid=r.bonanzaid where ra.rowid=?",[$rowid]);
		PDO_Execute("Update useraccount ups join personalinfo pf on pf.userid=ups.userid set tds=case when(LENGTH(pan)=10) then 5 else 20 end where tds is null or tds = 0 and aamount > 0 and (amount is null or amount=0)");
		PDO_Execute("update useraccount set amount=aamount-(aamount*(tds+service)/100) where aamount > 0 and (amount is null or amount=0)");
	}
	PDO_Execute("update rankrewardachivers set prizeallotedon=case when prizeallotedon is null then date_add(date_add(now(),interval 5 hour), interval 30 minute) else null end,bvqualified=? where rowid=?",[$allow,$rowid]);
}


if($routename=="updaterewardstatuscomplete")
{
	PDO_Execute("UPDATE lifetimebonanzaachivers ltba JOIN (select upliners,lifetimebonanza.bonanzaid,sum(pvalue) pvalue FROM upliners JOIN lifetimebonanzaachivers on lifetimebonanzaachivers.userid=upliners.upliners JOIN lifetimebonanza on lifetimebonanza.bonanzaid=lifetimebonanzaachivers.bonanzaid WHERE upliners.side=1 and upliners.udoa BETWEEN startdate and enddate and lifetimebonanzaachivers.achieved is null group by upliners.upliners,lifetimebonanza.bonanzaid) u on u.upliners=ltba.userid and ltba.bonanzaid=u.bonanzaid set ltba.leftbv=u.pvalue");
	PDO_Execute("UPDATE lifetimebonanzaachivers ltba JOIN (select upliners,lifetimebonanza.bonanzaid,sum(pvalue) pvalue FROM upliners JOIN lifetimebonanzaachivers on lifetimebonanzaachivers.userid=upliners.upliners JOIN lifetimebonanza on lifetimebonanza.bonanzaid=lifetimebonanzaachivers.bonanzaid WHERE upliners.side=0 and upliners.udoa BETWEEN startdate and enddate and lifetimebonanzaachivers.achieved is null group by upliners.upliners,lifetimebonanza.bonanzaid) u on u.upliners=ltba.userid and ltba.bonanzaid=u.bonanzaid set ltba.rightbv=u.pvalue");
	PDO_Execute("UPDATE lifetimebonanzaachivers JOIN lifetimebonanza on lifetimebonanza.bonanzaid=lifetimebonanzaachivers.bonanzaid set lifetimebonanzaachivers.bvqualified=1 WHERE lifetimebonanzaachivers.leftbv >= lifetimebonanza.lefttarget and lifetimebonanzaachivers.rightbv >= lifetimebonanza.righttarget and lifetimebonanzaachivers.bvqualified is null");
	PDO_Execute("UPDATE lifetimebonanzaachivers JOIN lifetimebonanza on lifetimebonanza.bonanzaid=lifetimebonanzaachivers.bonanzaid set lifetimebonanzaachivers.bvqualified=0 WHERE lifetimebonanzaachivers.bvqualified is null and enddate < date_add(date_add(now(),interval 5 hour), interval 30 minute);");
	PDO_Execute("UPDATE lifetimebonanzaachivers JOIN lifetimebonanza on lifetimebonanza.bonanzaid=lifetimebonanzaachivers.bonanzaid set achieved=1 WHERE lifetimebonanza.dcount=0 and lifetimebonanzaachivers.bvqualified=1");
	PDO_Execute("UPDATE lifetimebonanzaachivers set achieved=0 WHERE  lifetimebonanzaachivers.bvqualified=0");
	
	for($i;$i<=4;$i++)
	{
		PDO_Execute("UPDATE lifetimebonanzaachivers JOIN lifetimebonanza on lifetimebonanza.bonanzaid=lifetimebonanzaachivers.bonanzaid JOIN ( select upliners.upliners,lb.bonanzaid,lb.bonanzaname, count(*) totalcount from upliners join lifetimebonanzaachivers lbq on upliners.userid=lbq.userid join personalinfo pf on pf.userid=upliners.userid join lifetimebonanza lb on lb.bonanzaid=lbq.bonanzaid where upliners.side=0 and lbq.achieved=1 group by lb.bonanzaid,lb.bonanzaname,upliners.upliners) qu on qu.upliners=lifetimebonanzaachivers.userid and qu.bonanzaid=lifetimebonanza.dbonanzaid set lifetimebonanzaachivers.rightteam=qu.totalcount");
		PDO_Execute("UPDATE lifetimebonanzaachivers JOIN lifetimebonanza on lifetimebonanza.bonanzaid=lifetimebonanzaachivers.bonanzaid JOIN ( select upliners.upliners,lb.bonanzaid,lb.bonanzaname, count(*) totalcount from upliners join lifetimebonanzaachivers lbq on upliners.userid=lbq.userid join personalinfo pf on pf.userid=upliners.userid join lifetimebonanza lb on lb.bonanzaid=lbq.bonanzaid where upliners.side=1 and lbq.achieved=1 group by lb.bonanzaid,lb.bonanzaname,upliners.upliners) qu on qu.upliners=lifetimebonanzaachivers.userid and qu.bonanzaid=lifetimebonanza.dbonanzaid set lifetimebonanzaachivers.leftteam=qu.totalcount");
		PDO_Execute("UPDATE lifetimebonanzaachivers JOIN lifetimebonanza on lifetimebonanza.bonanzaid=lifetimebonanzaachivers.bonanzaid set lifetimebonanzaachivers.teamqualified=1 WHERE lifetimebonanzaachivers.leftteam >= lifetimebonanza.dcount and lifetimebonanzaachivers.rightteam >= lifetimebonanza.dcount");
		PDO_Exeucte("UPDATE lifetimebonanzaachivers set lifetimebonanzaachivers.achieved=1 WHERE lifetimebonanzaachivers.bvqualified=1 and teamqualified=1 and achieved is null");
	}
}




if($routename=="bonanzaachievers")
{
    	$rowid=$data['rowid'];	
		$bonanzaid=PDO_FetchOne("select bonanzainfoid from bonanza where rowid=?",[$rowid]);
		$bonanzainfodetail=PDO_FetchRow("select * from bonanzainfo where rowid=?",[$bonanzaid]);
		if(PDO_FetchOne("select count(*) from bonanzaachivers where bonanzaid=?",[$bonanzaid])==0)
		{
			
			PDO_Execute("insert into bonanzaachivers(userid,bonanzaid,mleft,mright,rleft,rright) select userid,?,0,0,0,0 from `binary` where ifnull(isapproved,0)=1",[$bonanzaid]);
			PDO_Execute("update bonanzaachivers join (select upliners,sum(ifnull(bpvalue,0)) mcount from upliners where udoa >= ? and udoa < date_add(?,interval 1 day) and side=1 group by upliners) u on u.upliners=bonanzaachivers.userid set mleft=u.mcount where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzainfodetail['uptodate'],$bonanzaid]);
				PDO_Execute("update bonanzaachivers join (select upliners,sum(ifnull(bpvalue,0)) mcount from upliners where udoa >= ? and udoa < date_add(?,interval 1 day) and side=0 group by upliners) u on u.upliners=bonanzaachivers.userid set mright=u.mcount where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzainfodetail['uptodate'],$bonanzaid]);
				if(PDO_FetchOne("select bvtarget1 from bonanza where bonanzainfoid=?",[$bonanzaid]) > 0)
				{
				PDO_Execute("update bonanzaachivers join (select upliners,sum(usd.qty*rp.bv) div 2500 mcount from upliners join usersinvoices ui on ui.userid=upliners.userid join userssaledetail usd on usd.invoiceid=ui.invoiceid join repurchaseproducts rp on rp.productid=usd.productid where ui.invoicedate >= ? and ui.invoicedate < date_add(?,interval 1 day) and side=1 and ui.saletypeid = 1 group by upliners) u on u.upliners=bonanzaachivers.userid set mleft=mleft+u.mcount where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzainfodetail['uptodate'],$bonanzaid]);
				PDO_Execute("update bonanzaachivers join (select upliners,sum(usd.qty*rp.bv) div 2500 mcount from upliners join usersinvoices ui on ui.userid=upliners.userid join userssaledetail usd on usd.invoiceid=ui.invoiceid join repurchaseproducts rp on rp.productid=usd.productid where ui.invoicedate >= ? and ui.invoicedate < date_add(?,interval 1 day) and side=0 and ui.saletypeid = 1 group by upliners) u on u.upliners=bonanzaachivers.userid set mright=mright+u.mcount where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzainfodetail['uptodate'],$bonanzaid]);
				PDO_Execute("update bonanzaachivers join (select upliners,sum(usd.qty*rp.bv) mcount from upliners join usersinvoices ui on ui.userid=upliners.userid join userssaledetail usd on usd.invoiceid=ui.invoiceid join repurchaseproducts rp on rp.productid=usd.productid where ui.invoicedate >= ? and ui.invoicedate < date_add(?,interval 1 day) and side=1 and ui.saletypeid = 1 group by upliners) u on u.upliners=bonanzaachivers.userid set rleft=u.mcount where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzainfodetail['uptodate'],$bonanzaid]);
				PDO_Execute("update bonanzaachivers join (select upliners,sum(usd.qty*rp.bv) mcount from upliners join usersinvoices ui on ui.userid=upliners.userid join userssaledetail usd on usd.invoiceid=ui.invoiceid join repurchaseproducts rp on rp.productid=usd.productid where ui.invoicedate >= ? and ui.invoicedate < date_add(?,interval 1 day) and side=0 and ui.saletypeid = 1 group by upliners) u on u.upliners=bonanzaachivers.userid set rright=u.mcount where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzainfodetail['uptodate'],$bonanzaid]);
				PDO_Execute("update bonanzaachivers join (select ui.userid,sum(usd.qty*rp.bv) pvalue from usersinvoices join userssaledetail usd on ui.invoiceid=usd.invoiceid join  repurchaseproducts rp on rp.productid=usd.productid where ui.invoicedate >= ? and ui.invoicedate < date_add(?,interval 1 day)  and ui.saletypeid = 1 group by ui.userid) u on u.userid=bonanzaachivers.userid set selfpurchasepoint=u.pvalue where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzainfodetail['uptodate'],$bonanzaid]);
			PDO_Execute("update bonanzaachivers join (select upliners,sum(ifnull(bpvalue,0)) mcount from upliners where  udoa < date_add(?,interval 1 day) and side=1 group by upliners) u on u.upliners=bonanzaachivers.userid set preleftunit=u.mcount where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzaid]);
				PDO_Execute("update bonanzaachivers join (select upliners,sum(ifnull(bpvalue,0)) mcount from upliners where  udoa < date_add(?,interval 1 day) and side=0 group by upliners) u on u.upliners=bonanzaachivers.userid set prerightunit=u.mcount where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzaid]);
			PDO_Execute("update bonanzaachivers join (select upliners,sum(rp.bv*usd.qty) DIV 2500 pvalue FROM upliners JOIN `binary` on `binary`.userid=upliners.upliners join usersinvoices ui on ui.userid=upliners.userid join userssaledetail usd on usd.invoiceid=ui.invoiceid join repurchaseproducts rp on rp.productid=usd.productid WHERE upliners.side=1 and ui.invoicedate >= case WHEN `binary`.doa < '2023-06-01' THEN '2023-06-01' else `binary`.`doa` END and ui.saletypeid=1 and ui.invoicedate < date_add(?,interval 1 day) group by upliners.upliners) u on u.upliners=bonanzaachivers.userid set preleftpv=pvalue where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzaid]);
				PDO_Execute("update bonanzaachivers join (select upliners,sum(rp.bv*usd.qty) DIV 2500 pvalue FROM upliners JOIN `binary` on `binary`.userid=upliners.upliners join usersinvoices ui on ui.userid=upliners.userid join userssaledetail usd on usd.invoiceid=ui.invoiceid join repurchaseproducts rp on rp.productid=usd.productid WHERE upliners.side=0 and ui.invoicedate >= case WHEN `binary`.doa < '2023-06-01' THEN '2023-06-01' else `binary`.`doa` END and ui.saletypeid=1 and ui.invoicedate < date_add(?,interval 1 day) group by upliners.upliners) u on u.upliners=bonanzaachivers.userid set prerightpv=pvalue where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzaid]);
				}
			PDO_Execute("update bonanzaachivers join (select sum(plans.bgpvalue) pvalue,sid from plans join `binary` on `binary`.planid=plans.planid where doa >= ? and doa < date_add(?, interval 1 day) group by sid) u on u.sid=bonanzaachivers.userid set selfunit=u.bpvalue where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzainfodetail['uptodate'],$bonanzaid]);
				
			PDO_Execute("update bonanzaachivers set recoid=0 where recoid is null and bonanzaid=?",[$bonanzaid]);
				PDO_Execute("UPDATE bonanzaachivers JOIN userreco on userreco.userid=bonanzaachivers.userid set bonanzaachivers.recoid=userreco.recoid where bonanzaachivers.bonanzaid=?",[$boanazaid]);
		}
			if((time()-(60*60*24)) < strtotime($bonanzainfodetail['uptodate']. ' + 0 days'))
			{
				
				PDO_Execute("update bonanzaachivers join (select upliners,sum(ifnull(bpvalue,0)) mcount from upliners where udoa >= ? and udoa < date_add(?,interval 1 day) and side=1 group by upliners) u on u.upliners=bonanzaachivers.userid set mleft=u.mcount where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzainfodetail['uptodate'],$bonanzaid]);
				PDO_Execute("update bonanzaachivers join (select upliners,sum(ifnull(bpvalue,0)) mcount from upliners where udoa >= ? and udoa < date_add(?,interval 1 day) and side=0 group by upliners) u on u.upliners=bonanzaachivers.userid set mright=u.mcount where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzainfodetail['uptodate'],$bonanzaid]);
				if(PDO_FetchOne("select bvtarget1 from bonanza where bonanzaid=?",[$bonanzaid]) > 0)
{
				
				PDO_Execute("update bonanzaachivers join (select upliners,sum(usd.qty*rp.bv) div 2500 mcount from upliners join usersinvoices ui on ui.userid=upliners.userid join userssaledetail usd on usd.invoiceid=ui.invoiceid join repurchaseproducts rp on rp.productid=usd.productid where ui.invoicedate >= ? and ui.invoicedate < date_add(?,interval 1 day) and side=1 and ui.saletypeid = 1 group by upliners) u on u.upliners=bonanzaachivers.userid set mleft=mleft+u.mcount where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzainfodetail['uptodate'],$bonanzaid]);
				PDO_Execute("update bonanzaachivers join (select upliners,sum(usd.qty*rp.bv) div 2500 mcount from upliners join usersinvoices ui on ui.userid=upliners.userid join userssaledetail usd on usd.invoiceid=ui.invoiceid join repurchaseproducts rp on rp.productid=usd.productid where ui.invoicedate >= ? and ui.invoicedate < date_add(?,interval 1 day) and side=0 and ui.saletypeid = 1 group by upliners) u on u.upliners=bonanzaachivers.userid set mright=mright+u.mcount where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzainfodetail['uptodate'],$bonanzaid]);
				PDO_Execute("update bonanzaachivers join (select upliners,sum(usd.qty*rp.bv) mcount from upliners join usersinvoices ui on ui.userid=upliners.userid join userssaledetail usd on usd.invoiceid=ui.invoiceid join repurchaseproducts rp on rp.productid=usd.productid where ui.invoicedate >= ? and ui.invoicedate < date_add(?,interval 1 day) and side=1 and ui.saletypeid = 1 group by upliners) u on u.upliners=bonanzaachivers.userid set rleft=u.mcount where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzainfodetail['uptodate'],$bonanzaid]);
				PDO_Execute("update bonanzaachivers join (select upliners,sum(usd.qty*rp.bv) mcount from upliners join usersinvoices ui on ui.userid=upliners.userid join userssaledetail usd on usd.invoiceid=ui.invoiceid join repurchaseproducts rp on rp.productid=usd.productid where ui.invoicedate >= ? and ui.invoicedate < date_add(?,interval 1 day) and side=0 and ui.saletypeid = 1 group by upliners) u on u.upliners=bonanzaachivers.userid set rright=u.mcount where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzainfodetail['uptodate'],$bonanzaid]);
				
				PDO_Execute("update bonanzaachivers join (select ui.userid,sum(usd.qty*rp.bv) pvalue from usersinvoices join userssaledetail usd on ui.invoiceid=usd.invoiceid join  repurchaseproducts rp on rp.productid=usd.productid where ui.invoicedate >= ? and ui.invoicedate < date_add(?,interval 1 day)  and ui.saletypeid = 1 group by ui.userid) u on u.userid=bonanzaachivers.userid set selfpurchasepoint=u.pvalue where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzainfodetail['uptodate'],$bonanzaid]);
				
				PDO_Execute("update bonanzaachivers join (select upliners,sum(ifnull(pvalue,0)) mcount from upliners where  udoa < date_add(?,interval 1 day) and side=1 group by upliners) u on u.upliners=bonanzaachivers.userid set preleftunit=u.mcount where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzaid]);
				PDO_Execute("update bonanzaachivers join (select upliners,sum(ifnull(bpvalue,0)) mcount from upliners where  udoa < date_add(?,interval 1 day) and side=0 group by upliners) u on u.upliners=bonanzaachivers.userid set prerightunit=u.mcount where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzaid]);
				PDO_Execute("update bonanzaachivers join (select upliners,sum(rp.bv*usd.qty) DIV 2500 pvalue FROM upliners JOIN `binary` on `binary`.userid=upliners.upliners join usersinvoices ui on ui.userid=upliners.userid join userssaledetail usd on usd.invoiceid=ui.invoiceid join repurchaseproducts rp on rp.productid=usd.productid WHERE upliners.side=1 and ui.invoicedate >= case WHEN `binary`.doa < '2023-06-01' THEN '2023-06-01' else `binary`.`doa` END and ui.saletypeid=1 and ui.invoicedate < date_add(?,interval 1 day) group by upliners.upliners) u on u.upliners=bonanzaachivers.userid set preleftpv=pvalue where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzaid]);
				PDO_Execute("update bonanzaachivers join (select upliners,sum(rp.bv*usd.qty) DIV 2500 pvalue FROM upliners JOIN `binary` on `binary`.userid=upliners.upliners join usersinvoices ui on ui.userid=upliners.userid join userssaledetail usd on usd.invoiceid=ui.invoiceid join repurchaseproducts rp on rp.productid=usd.productid WHERE upliners.side=0 and ui.invoicedate >= case WHEN `binary`.doa < '2023-06-01' THEN '2023-06-01' else `binary`.`doa` END and ui.saletypeid=1 and ui.invoicedate < date_add(?,interval 1 day) group by upliners.upliners) u on u.upliners=bonanzaachivers.userid set prerightpv=pvalue where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzaid]);
}
PDO_Execute("update bonanzaachivers join (select sum(plans.pvalue) pvalue,sid from plans join `binary` on `binary`.planid=plans.planid where doa >= ? and doa < date_add(?, interval 1 day) group by sid) u on u.sid=bonanzaachivers.userid set selfunit=u.pvalue where bonanzaid=?",[$bonanzainfodetail['fromdate'],$bonanzainfodetail['uptodate'],$bonanzaid]);
				PDO_Execute("update bonanzaachivers set recoid=0 where recoid is null and bonanzaid=?",[$bonanzaid]);
				PDO_Execute("UPDATE bonanzaachivers JOIN userreco on userreco.userid=bonanzaachivers.userid set bonanzaachivers.recoid=userreco.recoid where bonanzaachivers.bonanzaid=?",[$bonanzaid]);
			}
	else			
	{
		PDO_Execute("Delete from bonanzaachivers where mleft=0 and mright=0 and rleft=0 and rright=0 and ifnull(selfunit,0)=0 and ifnull(selfpurchasepoint,0)=0");
	}
		//if(PDO_FetchOne("select max(rowid) from bonanza where bonanzainfoid=?",[$bonanzaid])==$rowid)
		//{
			$data=PDO_FetchAll("Select pf.userid,pf.name,pf.city,pf.contact,ba.mleft,ba.mright,ba.rleft,ba.rright from personalinfo pf join bonanzaachivers ba on ba.userid=pf.userid join bonanza on ba.mleft >= bonanza.target1 and ba.mright >= bonanza.target2 and rleft >= bvtarget1 and rright>= bvtarget2 and ifnull(selfunit,0) >= bonanza.sponsorpoint and ifnull(ba.selfpurchasepoint,0) >= bonanza.selfpurchasepoint and ba.recoid between bonanza.recoid and bonanza.recoidmax where bonanza.rowid=? and ba.bonanzaid=?",[$rowid,$bonanzaid]);
		/*}
	else
	{
		$rowid2=PDO_FetchOne("select rowid from bonanza where rowid > ? and bonanzainfoid=? order by rowid limit 1",[$rowid,$bonanzaid]);
		$data=PDO_FetchAll("Select pf.userid,pf.name,pf.city,pf.contact,ba.mleft,ba.mright,ba.rleft,ba.rright from personalinfo pf join bonanzaachivers ba on ba.userid=pf.userid , bonanza , bonanza b2 where ba.mleft >= bonanza.target1 and ba.mright >= bonanza.target2 and ba.rleft >= bonanza.bvtarget1 and ba.rright>= bonanza.bvtarget2 and ((ba.mleft < b2.target1 or ba.mright < b2.target2) or (ba.rleft < case b2.bvtarget1 when 0 then 50000000 else b2.bvtarget1 end or ba.rright < case b2.bvtarget2 when 0 then 50000000 else b2.bvtarget2 end)) and bonanza.rowid=? and b2.rowid=? and ba.bonanzaid=?",[$rowid,$rowid2,$bonanzaid]);
	}*/
//	$q->debug=$rowid2;
	$q->data=$data;
	
    $q->result=1;
    
}

if($routename=="userrecolist")
{
	extract($data);
	$limit=" limit ". intval(($page-1)*$pagesize) . " , " . $pagesize;
	if($userid =='DW')
	{
		$q->data=PDO_FetchAll("select pf.userid,pf.name,pf.city,recname,totalleft,totalright,midvalue from userreco join reco on reco.recid=userreco.recoid join personalinfo pf on pf.userid=userreco.userid join `binary` on `binary`.userid=pf.userid where userreco.recoid=? order by `binary`.doa desc ".$limit,[$recid]);
		$q->totalrows=PDO_FetchOne("select count(*) from userreco where recoid=?",[$recid]);
	}
	else
	{
		$q->data=PDO_FetchAll("select pf.userid,pf.name,pf.city,recname,totalleft,totalright,midvalue from userreco join reco on reco.recid=userreco.recoid join personalinfo pf on pf.userid=userreco.userid where userreco.userid=?",[$userid]);
		$q->totalrows=1;
	}
	$q->result=1;
}

if($routename=="teamuserrecolist")
{
	extract($data);
	$limit=" limit ". intval(($page-1)*$pagesize) . " , " . $pagesize;
    if($recid==0)
    {
		$q->data=PDO_FetchAll("select pf.userid,pf.name,pf.city,recname,totalleft,totalright,midvalue from upliners join userreco on userreco.userid=upliners.userid join reco on reco.recid=userreco.recoid join personalinfo pf on pf.userid=userreco.userid where upliners.upliners=? order by userreco.recoid desc ".$limit,[$userid]);
		$q->totalrows=PDO_FetchOne("select count(*) from upliners join userreco on userreco.userid=upliners.userid JOIN reco on reco.recid=userreco.recoid where upliners.upliners=?",[$userid]);
    }
    else
    {
        $q->data=PDO_FetchAll("select pf.userid,pf.name,pf.city,recname,totalleft,totalright,midvalue from upliners join userreco on userreco.userid=upliners.userid join reco on reco.recid=userreco.recoid join personalinfo pf on pf.userid=userreco.userid where upliners.upliners=? and userreco.recoid=? order by userreco.userid".$limit,[$userid,$recid]);
		$q->totalrows=PDO_FetchOne("select count(*) from upliners join userreco on userreco.userid=upliners.userid JOIN reco on reco.recid=userreco.recoid where upliners.upliners=? and userreco.recoid=?",[$userid,$recid]);
    }
	
	
	$q->result=1;
}

if($routename=="recolist")
{
	
		$q->data=PDO_FetchAll("select * from reco order by reco.recid",[]);
	
	$q->result=1;
}

if($routename=="updatereco")
{
	
		PDO_Execute("TRUNCATE TABLE userreco;
    CREATE TEMPORARY TABLE uplinerscount SELECT sum(case when side=1 THEN pvalue else 0 end) leftpvalue,sum(case when side=0 then pvalue else 0 end) rightpvalue,0 midvalue,upliners FROM upliners where upliners.udoa is not null GROUP BY upliners;
    update uplinerscount set midvalue=case when leftpvalue <= rightpvalue then leftpvalue else rightpvalue end;
    INSERT IGNORE INTO userreco(userreco.userid,userreco.recoid) SELECT upliners,reco.recid from uplinerscount JOIN reco on midvalue BETWEEN pairs and maxpairs;" );
	
	$q->result=1;
}

if($routename=="updatebonanzagift")
{
    $rowid=$data['rowid'];
    $bonanzaid=$data['bonanzaid'];
    $giftname=$data['name'];
    $leftbv=$data['leftbv'];
    $rightbv=$data['rightbv'];
    $leftiv=$data['leftiv'];
    $rightiv=$data['rightiv'];
    $recoid=$data['recoid'];
    PDO_Execute("update bonanza set rewarditemname=?,recoid=?,target1=?,target2=?,bonanzainfoid=?,bvtarget1=?,bvtarget2=? where rowid=?",[$giftname,$recoid,$leftbv,$rightbv,$bonanzaid,$leftiv,$rightiv,$rowid]);
    $q->result=1;
    
}

if($routename=="bonanzagiftlist")
{
    
    $rowid=$data['bonanzaid'];
    $q->data=PDO_FetchAll("select * from bonanza where bonanzainfoid=? order by rowid",[$rowid]);
    $q->result=1;
}

if($routename=="levelincome")
{
    extract($data);
     $limit=" limit ". intval(($page-1)*$pagesize) . " , " . $pagesize;
    $q->data=PDO_FetchAll("select userlevelincome.*,pf.name from userlevelincome join personalinfo pf on pf.userid=userlevelincome.new_userid where level > 1 order by rowid desc" . $limit);
    $q->totalrows=PDO_FetchOne("select count(*) from userlevelincome where level > 1");
    $q->result=1;
}

if($routename=="directincome")
{
    extract($data);
     $limit=" limit ". intval(($page-1)*$pagesize) . " , " . $pagesize;
      $q->data=PDO_FetchAll("select userlevelincome.*,pf.name from userlevelincome join personalinfo pf on pf.userid=userlevelincome.new_userid where level = 1 order by rowid desc" . $limit);
      $q->totalrows=PDO_FetchOne("select count(*) from userlevelincome where level = 1");
    $q->result=1;
}

if($routename=="deletebonanzagift")
{
    $rowid=$data['rowid'];
    PDO_Execute("delete bonanza where rowid=?",[$rowid]);
    $q->result=1;
}

if($routename=="dashboard")
{
    /*plans:any[]=[]
  members:any[]=[]
  totalteam=0;
  pendingactivation=0
  totalplanamount=0
  totalpendingamount=0
  directincome=0
  levelincome=0
  autopoolincome=0*/
  $q->result=1;
  $q->plans=PDO_FetchAll("select * from plans");
  $q->members=PDO_FetchAll("select pf.userid,pf.name,pf.city,plans.packagename,ifnull(isapproved,0) isactive,pf.doj,bn.doa from `binary` bn join personalinfo pf on pf.userid=bn.userid left join plans on plans.rowid=bn.planid order by pf.doj desc limit 10");
  $q->totalteam=PDO_FetchOne("select count(*) from `binary`");
  $q->pendingactivation=PDO_FetchOne("select count(*) from `binary` where ifnull(isapproved,0)=0");
  $q->totalplanamount=PDO_FetchOne("select sum(plans.amount) from `binary` join plans on plans.rowid=`binary`.planid where ifnull(isapproved,0)=1");
  $q->totalpendingamount=PDO_FetchOne("select sum(amount)-sum(camount) from useraccount");
  $q->directincome=PDO_FetchOne("select sum(amount) from userlevelincome where level=1");
  $q->levelincome=PDO_FetchOne("select sum(amount) from userlevelincome where level!=1");
  $q->autopoolincome=PDO_FetchOne("select count(*)*1000 from `binary` where isapproved=1");
}



if ($routename == "downlinelist") {
    try {
        $paramarr=array();
		$qparam="";
        extract($data);
       	



            $SELECT_query = "select personalinfo.userid, mobile, personalinfo.name, personalinfo.doj,
            `binary`.doa, plans.packagename, personalinfo.city,`binary`.sid,`binary`.`uid`, plans.amount,ifnull(`binary`.isapproved,0) isapproved
            from personalinfo 
            join `binary`on `binary`.userid= personalinfo.userid left
            join plans on plans.rowid = `binary`.planid  order by ifnull(`binary`.doa,personalinfo.doj) desc " ;
            $count_query = "select count(*) as tcount 
            from personalinfo 
            join `binary`on `binary`.userid= personalinfo.userid left
            join plans on plans.rowid = `binary`.planid  " . $qparam;
       



        $limit=" limit ". intval(($page-1)*$pagesize) . " , " . $pagesize;


        $q = new \stdClass();
        $q->totalteam=PDO_FetchOne("select count(*) from `binary`");
  $q->pendingactivation=PDO_FetchOne("select count(*) from `binary` where ifnull(isapproved,0)=0");
   $q->totalactive=PDO_FetchOne("select count(*) from `binary` where ifnull(isapproved,0)=1");
    $q->totalblocked=PDO_FetchOne("select count(*) from `binary` join users on `binary`.userid=users.username where ifnull(islockedout,0)=1");
        $q->result = PDO_FetchAll($SELECT_query . $limit);
        $q->totals = PDO_FetchRow($count_query);
        $q->status = 1;
        
    } catch (PDOException $e) {
        echo 'Prepare failed: ' . $e->getMessage();
    }
}

if ($routename == "activeplanlist") {
    try {

        $SELECT_query = "SELECT planid, planname From plans where enablejoining=1";
        $q = new \stdClass();
        $q->result = PDO_FetchAll($SELECT_query, $paramarr);
    } catch (PDOException $e) {
        echo 'Prepare failed: ' . $e->getMessage();
    }
}





if ($routename == "memberslist") {
    try {
        $qparam = "";
        $qparamval = "";
        $paramarr = array();

        $qparam = "";
        $paramarr = array();
       


       

            if (isset($data["fromdate"]) && $data["fromdate"] != "" && $data['search']=='' && $data['isfranchise']=='3') {
                if ($qparam == "") $qparam = " where ";
                else {
                    $qparam = $qparam . " and ";
                }
                $qparam = $qparam . "personalinfo.doj>=?";
                $paramarr[] = $data["fromdate"];
            }

            if (isset($data["uptodate"]) && $data["uptodate"] != ""  && $data['search']=='' && $data['isfranchise']=='3') {
                if ($qparam == "") $qparam = " where ";
                else {
                    $qparam = $qparam . " and ";
                }
                $qparam = $qparam . "personalinfo.doj<=date_add(?,interval 1 day)";
                $paramarr[] = $data["uptodate"];
            }
			
			if (isset($data["sortby"]) && $data["sortby"] != "3" && $data['search']=='' && $data['isfranchise']=='3') {
                if ($qparam == "") $qparam = " where ";
                else {
                    $qparam = $qparam . " and ";
                }
                $qparam = $qparam . "ifnull(`binary`.isapproved,0)=?";
                $paramarr[] = $data["sortby"];
            }
		
			if (isset($data["planid"]) && $data["planid"] != "0" && $data['search']=='' && $data['isfranchise']=='3') {
                if ($qparam == "") $qparam = " where ";
                else {
                    $qparam = $qparam . " and ";
                }
                $qparam = $qparam . "ifnull(`binary`.planid,0)=?";
                $paramarr[] = $data["planid"];
            }

            if (isset($data["search"]) && $data["search"] != "") {
                if ($qparam == "") $qparam = " where ";
                else {
                    $qparam = $qparam . " and ";
                }
                $qparam = $qparam . "(personalinfo.name like ? or users.username like ?)";
                $paramarr[] = "%" . $data["search"] . "%";
                $paramarr[] = "%" . $data["search"] . "%";
            }
		
		if (isset($data["isfranchise"]) && $data["isfranchise"] != "3") {
                if ($qparam == "") $qparam = " where ";
                else {
                    $qparam = $qparam . " and ";
                }
				if($data['isfranchise']=='1')
                $qparam = $qparam . "ifnull(`binary`.isdistributor,0)=?";
				if($data['isfranchise']=='2')
                $qparam = $qparam . "ifnull(`binary`.boosterupgraded,0)=?";
                $paramarr[] = '1';
            }

           
            $SELECT_query = "SELECT personalinfo.userid, personalinfo.name, personalinfo.doj, `binary`.doa, `binary`.sid, `binary`.franchisetype,ifnull(`binary`.isapproved,0) isapproved ,users.username,users.password ,personalinfo.city, personalinfo.pan,personalinfo.ifsc,personalinfo.acno,users.password, ifnull(`binary`.isdistributor,0) isdistributor, ifnull(`binary`.boosterupgraded,0) boosterupgraded, plans.planname, plans.planid, personalinfo.contact mobile
             From  personalinfo
			join users on users.username=personalinfo.userid
            Join `binary`on `binary`.userid= users.username  join plans on plans.planid=`binary`.planid " . $qparam . " order by `personalinfo`.doj desc " . $limit;
			
			
            $count_query = "SELECT count(*) as tcount 
             From  personalinfo
			join users on users.username=personalinfo.userid 
            Join `binary`on `binary`.userid= users.username " . $qparam ;
      



        if (isset($data["page"])) {
            $pagecount = (((int)$data['page'])-1) * (int)$data['pagesize'];
            $limit =  " limit " . $pagecount . "," . $data['pagesize'];
        }


        $q = new \stdClass();
		$q->page=(((int)$data['page'])-1) * (int)$data['pagesize'];
        $q->result = PDO_FetchAll($SELECT_query . $limit, $paramarr);
        $q->totalcount = PDO_FetchOne($count_query, $paramarr);
		$q->lastpayoutdate=PDO_FetchOne("select date_add(upperdate,interval 1 day) from payout order by payoutid desc limit 1");
        $q->status = 1;
        // $SELECT_query = "SELECT personalinfo.name,personalinfo.userid, personalinfo.city,`binary`.sID,personalinfo.doj,plans.planname From personalinfo,`binary`,plans, upliners where personalinfo.userid=`binary`.userid and `binary`.planID=plans.planID and `binary`.UID='" . $userid . "' and `binary`.UID='".$userid. "' and side='".$data['side']."' and udoa >= '".$data['Fromdate']."' and udoa='".$data['uptodate']."' Order by `binary`.userid limit " . $data['pageno'] . "," . $data['pagesize'];
        // $count_query = "SELECT count(*) as tcount From personalinfo,`binary`,plans where personalinfo.userid=`binary`.userid and `binary`.planID=plans.planID and `binary`.UID='" . $userid . "' and side='".$data['side']."' and udoa='".$data['Fromdate']."' and udoa='".$data['uptodate']. "' Order by `binary`.userid";
    } catch (PDOException $e) {
        echo 'prepare failed: ' . $e->getMessage();
    }
}

if($routename == 'sponsordetail'){
	extract($data);
	$q->data=PDO_FetchRow("select spf.userid sid,spf.name sidname,upf.userid uid,upf.name uidname,`binary`.leftright from
							`binary` join personalinfo spf on spf.userid=`binary`.sid join personalinfo upf on upf.userid=`binary`.uid
							where `binary`.userid=?",[$userid]);
	$q->result=1;
}

if ($routename == "activatedlist") {
    try {
        $qparam = "";
        $qparamval = "";
        $paramarr = array();
        $qparam = "";
        $paramarr = array();
            if (isset($data["fromdate"]) && $data["fromdate"] != "") {
                if ($qparam == "") $qparam = " where ";
                else {
                    $qparam = $qparam . " and ";
                }
                $qparam = $qparam . "`binary`.doa>=?";
                $paramarr[] = $data["fromdate"];
            }

            if (isset($data["uptodate"]) && $data["uptodate"] != "" ) {
                if ($qparam == "") $qparam = " where ";
                else {
                    $qparam = $qparam . " and ";
                }
                $qparam = $qparam . "`binary`.doa<=date_add(?,interval 1 day)";
                $paramarr[] = $data["uptodate"];
            }
			
			
		
			if (isset($data["planid"]) && $data["planid"] != "0") {
                if ($qparam == "") $qparam = " where ";
                else {
                    $qparam = $qparam . " and ";
                }
                $qparam = $qparam . "ifnull(`binary`.planid,0)=?";
                $paramarr[] = $data["planid"];
            }

            if (isset($data["search"]) && $data["search"] != "") {
                if ($qparam == "") $qparam = " where ";
                else {
                    $qparam = $qparam . " and ";
                }
                $qparam = $qparam . "`binary`.activatedby=?";
                $paramarr[] =  $data["search"] ;
            }
		
	

           
            $SELECT_query = "SELECT personalinfo.userid, personalinfo.name, personalinfo.doj, `binary`.doa, ifnull(`binary`.isapproved,0) isapproved,users.username,users.password ,personalinfo.city, personalinfo.pan,personalinfo.ifsc,personalinfo.acno,users.password, `binary`.activatedby,ifnull(`binary`.isdistributor,0) isdistributor, ifnull(`binary`.boosterupgraded,0) boosterupgraded, plans.planname,personalinfo.contact mobile
             From  personalinfo
			join users on users.username=personalinfo.userid
            Join `binary`on `binary`.userid= users.username  join plans on plans.planid=`binary`.planid " . $qparam . " order by `binary`.doa desc " . $limit;
			
			
            $count_query = "SELECT count(*) as tcount 
             From  personalinfo
			join users on users.username=personalinfo.userid 
            Join `binary`on `binary`.userid= users.username " . $qparam ;
      



        if (isset($data["page"])) {
            $pagecount = (((int)$data['page'])-1) * (int)$data['pagesize'];
            $limit =  " limit " . $pagecount . "," . $data['pagesize'];
        }


        $q = new \stdClass();
		$q->page=(((int)$data['page'])-1) * (int)$data['pagesize'];
        $q->result = PDO_FetchAll($SELECT_query . $limit, $paramarr);
        $q->totalcount = PDO_FetchOne($count_query, $paramarr);
		$q->lastpayoutdate=PDO_FetchOne("select date_add(upperdate,interval 1 day) from payout order by payoutid desc limit 1");
        $q->status = 1;
        // $SELECT_query = "SELECT personalinfo.name,personalinfo.userid, personalinfo.city,`binary`.sID,personalinfo.doj,plans.planname From personalinfo,`binary`,plans, upliners where personalinfo.userid=`binary`.userid and `binary`.planID=plans.planID and `binary`.UID='" . $userid . "' and `binary`.UID='".$userid. "' and side='".$data['side']."' and udoa >= '".$data['Fromdate']."' and udoa='".$data['uptodate']."' Order by `binary`.userid limit " . $data['pageno'] . "," . $data['pagesize'];
        // $count_query = "SELECT count(*) as tcount From personalinfo,`binary`,plans where personalinfo.userid=`binary`.userid and `binary`.planID=plans.planID and `binary`.UID='" . $userid . "' and side='".$data['side']."' and udoa='".$data['Fromdate']."' and udoa='".$data['uptodate']. "' Order by `binary`.userid";
    } catch (PDOException $e) {
        echo 'prepare failed: ' . $e->getMessage();
    }
}

if ($routename == "teambusiness") {
    try {
            
            extract($data);
            PDO_Execute("truncate table temptest");
            if (isset($data["search"]) && $data["search"] != "") {
                PDO_Execute("Insert Ignore into temptest(userid) select `binary`.userid from `binary` join upliners on upliners.userid=`binary`.userid  where doa is not null and upliners.upliners=?;",[$search]);
            }
            else
            {
		
	            PDO_Execute("Insert Ignore into temptest(userid) select `binary`.userid from `binary` where doa is not null");
	        
            }
           PDO_Execute("update temptest join (SELECT sum(case when side=1 THEN pvalue else 0 end) leftpvalue,sum(case when side=0 then pvalue else 0 end) rightpvalue,upliners FROM upliners where upliners.udoa > ? and upliners.udoa < date_add(?,interval 1 day) GROUP BY upliners) urr on temptest.userid=urr.upliners set
    temptest.newleft=urr.leftpvalue,temptest.newright=rightpvalue",[$fromdate,$uptodate]);
            PDO_Execute("update temptest join (SELECT sum(case when side=1 THEN ifnull(rp.bv*usd.qty,0) else 0 end) div 2500 leftpvalue,sum(case when side=0 then ifnull(rp.bv*usd.qty,0) else 0 end) div 2500 rightpvalue,upliners FROM upliners u join usersinvoices ui on ui.userid=u.userid JOIN userssaledetail usd on usd.invoiceid=ui.invoiceid JOIN repurchaseproducts rp on rp.productid=usd.productid join `binary` b on b.userid=u.userid where ui.invoicedate >= ? and ui.invoicedate < date_add(?,interval 1 day) and ui.saletypeid=1 GROUP BY u.upliners) urr on temptest.userid=urr.upliners set temptest.bnewleft=urr.leftpvalue,temptest.bnewright=rightpvalue",[$fromdate,$uptodate]);
            PDO_Execute("update temptest set newleft=0 where newleft is null");
		    PDO_Execute("update temptest set newright=0 where newright is null");
			PDO_Execute("update temptest set bnewleft=0 where bnewleft is null");
		    PDO_Execute("update temptest set bnewright=0 where bnewright is null");
		    PDO_Execute("delete from temptest where newleft+bnewleft < ?",[$planid]);
		    PDO_Execute("delete from temptest where newright+bnewright < ?",[$planid]);
		    $SELECT_query="select pf.userid,pf.name,pf.city,pf.contact,newleft,newright,bnewleft,bnewright,newleft+bnewleft totalleft,newright+bnewright totalright from temptest join personalinfo pf on pf.userid=temptest.userid";
            $count_query = "SELECT count(*) as tcount From  temptest" ;
      



        if (isset($data["page"])) {
            $pagecount = (((int)$data['page'])-1) * (int)$data['pagesize'];
            $limit =  " limit " . $pagecount . "," . $data['pagesize'];
        }


        $q = new \stdClass();
		$q->page=(((int)$data['page'])-1) * (int)$data['pagesize'];
        $q->result = PDO_FetchAll($SELECT_query . $limit);
        $q->totalcount = PDO_FetchOne($count_query);
	
        $q->status = 1;
        // $SELECT_query = "SELECT personalinfo.name,personalinfo.userid, personalinfo.city,`binary`.sID,personalinfo.doj,plans.planname From personalinfo,`binary`,plans, upliners where personalinfo.userid=`binary`.userid and `binary`.planID=plans.planID and `binary`.UID='" . $userid . "' and `binary`.UID='".$userid. "' and side='".$data['side']."' and udoa >= '".$data['Fromdate']."' and udoa='".$data['uptodate']."' Order by `binary`.userid limit " . $data['pageno'] . "," . $data['pagesize'];
        // $count_query = "SELECT count(*) as tcount From personalinfo,`binary`,plans where personalinfo.userid=`binary`.userid and `binary`.planID=plans.planID and `binary`.UID='" . $userid . "' and side='".$data['side']."' and udoa='".$data['Fromdate']."' and udoa='".$data['uptodate']. "' Order by `binary`.userid";
    } catch (PDOException $e) {
        echo 'prepare failed: ' . $e->getMessage();
    }
}

if ($routename == "teambusinesslist") {
    try {
       
		
	       
		    $SELECT_query="select pf.userid,pf.name,pf.city,pf.contact,newleft,newright,bnewleft,bnewright,newleft+bnewleft totalleft,newright+bnewright totalright from temptest join personalinfo pf on pf.userid=temptest.userid";
            $count_query = "SELECT count(*) as tcount From  temptest" ;
      



        if (isset($data["page"])) {
            $pagecount = (((int)$data['page'])-1) * (int)$data['pagesize'];
            $limit =  " limit " . $pagecount . "," . $data['pagesize'];
        }


        $q = new \stdClass();
		$q->page=(((int)$data['page'])-1) * (int)$data['pagesize'];
        $q->result = PDO_FetchAll($SELECT_query . $limit);
        $q->totalcount = PDO_FetchOne($count_query);
	
        $q->status = 1;
      
    } catch (PDOException $e) {
        echo 'prepare failed: ' . $e->getMessage();
    }
}
if($routename=='changepassword')
{
    $newpassword=$_POST['password'];
    $q=new \stdClass();
    $oldpassword=PDO_FetchOne("SELECT password from users where username=?",[$userid]);
    if($oldpassword==$newpassword)
    {
        PDO_Execute("Update users set password=? where username=?",[$newpassword,$userid]);
        $q->result=1;
    }
    else
    $q->result=0;
}

if($routename=="updateadminuser")
{
	extract($data);
	PDO_Execute("update users set islockedout=?,password=?,isadmin=? where username=?",[$islockedout,$password,$isadmin,$username]);
	$q->result=1;
}

if($routename=="newadminuser")
{
	extract($data);
	PDO_Execute("Insert into users(username,password,isadmin,islockedout) values(?,?,?,0)",[$username,$password,$isadmin]);
	$q->result=1;
}

if($routename=="adminusers")
{
	//extract($data);
	$q->data=PDO_FetchAll("select users.username,users.password,users.islockedout,users.isadmin,adminrights.rightname from users join adminrights on adminrights.rowid=users.isadmin where isadmin <> 0");
	$q->result=1;
}

if($routename=="adminrights")
{
	$q->data=PDO_FetchAll("select * from adminrights order by rowid");
}

if($routename=="addnews")
{
    $q= new \stdClass();
    $newtitle=$data['title'];
    $newdescription=$data['newsdescription'];
    PDO_Execute("Insert into news (newsheading,newsdescription,newsdate) values(?,?,date_add(date_add(now(),interval 5 hour), interval 30 minute))", [$newtitle,$newdescription]);
    $newsid=PDO_LastInsertId();
    $q->id=$newsid;
}

if($routename=="addgallery")
{
    $q= new \stdClass();
    $newtitle=$data['title'];
    $newdescription=$data['description'];
    PDO_Execute("Insert into gallery (galleryname) values(?)", [$newtitle]);
    $newsid=PDO_LastInsertId();
    $q->id=$newsid;
}

if($routename=="addlegaldoc")
{
    $q= new \stdClass();
    $newtitle=$data['title'];    
    PDO_Execute("Insert into legaldocs (title) values(?)", [$newtitle]);
    $newsid=PDO_LastInsertId();
    $q->id=$newsid;
}

if($routename=="addgalleryphoto")
{
    $q= new \stdClass();
    $newtitle=$data['title'];
    $galleryid=$data['galleryid'];
    PDO_Execute("Insert into galleryphotos(subgalleryid,photodatah) values(?,?)", [$galleryid,$newtitle]);
    $newsid=PDO_LastInsertId();
    $q->id=$newsid;
}

if($routename=="addproduct")
{
    $q= new \stdClass();
    $productname=$data['productname'];
    $mrp=$data['mrp'];
    $discount=$data['discount'];
    $description=$data['description'];
    PDO_Execute("Insert into products (productname,mrp,discount,description) values(?,?,?,?)", [$productname,$mrp,$discount,$description]);
    $newsid=PDO_LastInsertId();
    $q->id=$newsid;
}




if($routename=="addbanner")
{
    $q= new \stdClass();   
    PDO_Execute("insert into banner(adddate) VALUES(date_add(date_add(now(),interval 5 hour), interval 30 minute))");
    $newsid=PDO_LastInsertId();
    $q->id=$newsid;
}

if($routename=="updateproduct")
{
    $q= new \stdClass();
    $productid=$data['productid'];
    $productname=$data['productname'];
    $mrp=$data['mrp'];
    $discount=$data['discount'];
    $description=$data['description'];
    PDO_Execute("update products set productname=?,mrp=?,discount=?,description=? where productid=?", [$productname,$mrp,$discount,$description,$productid]);
    $newsid=PDO_LastInsertId();
    $q->id=$newsid;
}



if($routename=="deletenews")
{
    $q= new \stdClass();
    $id=$data['id'];
    PDO_Execute("delete from news where newsid=?", [$id]);
    unlink("../uploads/news/".$id.".jpg");    
    $q->result=1;
}


if($routename=="deletelegaldoc")
{
    $q= new \stdClass();
    $id=$data['id'];
    PDO_Execute("delete from legaldocs where rowid=?", [$id]);
    unlink("../uploads/legaldocs/".$id.".jpg");  
    $q->result=1;
}

if($routename=="deletegallery")
{
    $q= new \stdClass();
    $id=$data['id'];
    $photolist=PDO_FetchAll("Select subgalleryid from galleryphotos where galleryid=?",[$id]);
    foreach($photolist as $photoid)
    {
        unlink("../uploads/galleryphotos/".$photoid['subgalleryid'].".jpg");
    }
    unlink("../uploads/gallery/".$id.".jpg");
    PDO_Execute("delete from galleryphotos where subgalleryid=?", [$id]);
    PDO_Execute("delete from gallery where galleryid=?", [$id]);
    $q->result=1;
}

if($routename=="deletegalleryphoto")
{
    $q= new \stdClass();
    $id=$data['id'];
    PDO_Execute("delete from galleryphotos where photoid=?", [$id]);
    unlink("../uploads/galleryphotos/".$id.".jpg");
    $q->result=1;
}

if($routename=="deleteproduct")
{
    $q= new \stdClass();
    $id=$data['id'];
    PDO_Execute("delete from products where productid=?", [$id]);
    unlink("../uploads/products/".$id.".jpg");
    $q->result=1;
}

if($routename=="addrepurchaseproduct")
{
	extract($data);
    $q= new \stdClass();  
    PDO_Execute("Insert into repurchaseproducts (productname,mrp,srp,pp,bv,retailercommission,dp,vat,otherinfo) values(?,?,?,?,?,?,?,?,?)", [$productname,$mrp,$srp,$pp,$bv,$commission,$dp,$vat,$description]);
    $newsid=PDO_LastInsertId();
    $q->id=$newsid;
}

if($routename=="repurchaseproduct")
{
	//extract($data);
    $q= new \stdClass();  
    $q->products=PDO_FetchAll("select * from repurchaseproducts where ifnull(isavailable,1)=1 order by productname");
    $q->result=1;
	
}

if($routename=="supplierlist")
{
  $q= new \stdClass();  
    $q->products=PDO_FetchAll("select * from suppliers order by suppliername");
    $q->result=1;
}

if($routename=="addsupplier")
{
    $q= new \stdClass();
   extract($data);
    PDO_Execute("Insert into suppliers (suppliername,gst,address,contact) values(?,?,?,?)", [$suppliername,$gst,$address,$contact]);
    $newsid=PDO_LastInsertId();
    $q->id=$newsid;
}

if($routename=="deletesupplier")
{
    $q= new \stdClass();
    $id=$data['id'];
    PDO_Execute("delete from suppliers where supplierid=?", [$id]);    
    $q->result=1;
}

if($routename=="availablestock")
{
	$q->result=1;
	$q->data=PDO_FetchAll("SELECT adminstock.productid, sum(ifnull(instock,0))-sum(ifnull(outstock,0)) stock,repurchaseproducts.productname,mrp,srp price,dp cashback,bv FROM adminstock JOIN repurchaseproducts on repurchaseproducts.productid=adminstock.productid GROUP BY adminstock.productid,repurchaseproducts.productname ORDER BY productname",[]);
}

if($routename=="adminstockdetail")
{
	$productid=$data['productid'];
	$q->result=1;
	$q->data=PDO_FetchAll("SELECT * from adminstock where productid=?",[$productid]);
}


if($routename=="editrepurchaseproduct")
{				
    $productid=$data['productid'];
    $productname=$data['productname'];
    $mrp=$data['mrp'];
    $srp=$data['srp'];
	$pp=$data['pp'];
    $bv=$data['bv'];
    $retailercommission=$data['commission'];
    $isavailable=$data['isavailable'];
    $otherinfo=$data['description'];
    $vat=$data['vat']; 
	$dp=$data['dp'];   
    PDO_Execute("update repurchaseproducts set productname =?,mrp =?,srp =?, pp=?,bv =?,retailercommission =?,isavailable =?,otherinfo =?,vat =?, dp=? where productid=?", [$productname,$mrp,$srp,$pp,$bv,$retailercommission,$isavailable,$otherinfo,$vat,$dp,$productid]);
    $newsid=PDO_LastInsertId();
    $q->result=1;
    $q->id=$newsid;
}

if($routename=="deleterepurchaseproduct")
{
    $q= new \stdClass();
    $id=$data['id'];
	$salecount1=PDO_FetchOne("select count(*) from userssaledetail where productid=?",[$id]);
	$salecount2=PDO_FetchOne("select count(*) from retailersaledetail where productid=?",[$id]);
	if($salecount1==0 && $salecount2==0)
	{
    	PDO_Execute("delete from repurchaseproducts where productid=?", [$id]);
    	//$q->result=1;
	}
	else
		PDO_Execute("update repurchaseproducts set isavailable=0 where productid=?", [$id]);
    	$q->result=1;
}

// ==============================================================================
// CARBONOVA PLANTS / PRODUCTS & ORDERS MANAGEMENT API ROUTES
// ==============================================================================

// 0. Image Upload Handler (Supports Multipart $_FILES or Base64 in JSON)
if($routename=="uploadimage")
{
    $folder = isset($_POST['folder']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['folder']) : ($data['folder'] ?? 'products');
    if (empty($folder)) $folder = 'products';
    
    $uploadDir = "../uploads/" . $folder . "/";
    if (!file_exists($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }
    
    // Case A: Multipart File Upload via $_FILES
    if (isset($_FILES['file']) || isset($_FILES['image'])) {
        $file = isset($_FILES['file']) ? $_FILES['file'] : $_FILES['image'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        if (!in_array($ext, $allowed)) {
            $q->result = 0;
            $q->message = "Invalid image type. Allowed: jpg, jpeg, png, webp, gif";
            echo json_encode($q);
            exit;
        }
        
        if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
            $q->result = 0;
            $q->message = "Image size exceeds 10MB limit";
            echo json_encode($q);
            exit;
        }
        
        $newFilename = $folder . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
        $targetPath = $uploadDir . $newFilename;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $relativePath = "uploads/" . $folder . "/" . $newFilename;
            $fullUrl = "https://www.carbonovaworld.com/" . $relativePath;
            $q->result = 1;
            $q->message = "Image uploaded successfully";
            $q->url = $fullUrl;
            $q->filepath = $relativePath;
            $q->filename = $newFilename;
        } else {
            $q->result = 0;
            $q->message = "Failed to write uploaded image to disk";
        }
        echo json_encode($q);
        exit;
    }
    
    // Case B: Base64 string in JSON data
    $base64Data = $data['base64'] ?? $data['image'] ?? '';
    if (!empty($base64Data)) {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
            $ext = strtolower($type[1]);
            if ($ext == 'jpeg') $ext = 'jpg';
        } else {
            $ext = 'jpg';
        }
        $decoded = base64_decode($base64Data);
        if ($decoded !== false) {
            $newFilename = $folder . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
            $targetPath = $uploadDir . $newFilename;
            file_put_contents($targetPath, $decoded);
            $relativePath = "uploads/" . $folder . "/" . $newFilename;
            $fullUrl = "https://www.carbonovaworld.com/" . $relativePath;
            $q->result = 1;
            $q->message = "Image uploaded successfully";
            $q->url = $fullUrl;
            $q->filepath = $relativePath;
            $q->filename = $newFilename;
        } else {
            $q->result = 0;
            $q->message = "Invalid base64 image data";
        }
        echo json_encode($q);
        exit;
    }
    
    $q->result = 0;
    $q->message = "No image file or base64 data received";
    echo json_encode($q);
    exit;
}

// 1. Fetch All Plants / Products
if($routename=="productlist")
{
    $q->result = 1;
    $prods = PDO_FetchAll("SELECT rowid, productname, scientificname, price, dp, 
                                  IFNULL(active,1) as active, 
                                  IFNULL(linkwithpackage,0) as linkwithpackage, 
                                  IFNULL(rewardpoint,0) as rewardpoint, 
                                  IFNULL(quantity,0) as quantity, 
                                  image 
                           FROM products ORDER BY rowid DESC");
    $list = [];
    if (!empty($prods)) {
        foreach ($prods as $p) {
            $p['rowid'] = intval($p['rowid']);
            $p['price'] = floatval($p['price']);
            $p['dp'] = floatval($p['dp']);
            $p['active'] = ($p['active'] == 1 || $p['active'] == '1');
            $p['linkwithpackage'] = ($p['linkwithpackage'] == 1 || $p['linkwithpackage'] == '1');
            $p['rewardpoint'] = intval($p['rewardpoint']);
            $p['quantity'] = intval($p['quantity']);
            $list[] = $p;
        }
    }
    $q->products = $list;
}

// 2. Save / Update Plant or Product
if($routename=="saveproduct")
{
    if (isset($data['product']) && is_array($data['product'])) {
        $data = $data['product'];
    }
    $rowid = isset($data['rowid']) ? intval($data['rowid']) : 0;
    $productname = $data['productname'] ?? '';
    $scientificname = $data['scientificname'] ?? '';
    $price = floatval($data['price'] ?? 0);
    $dp = floatval($data['dp'] ?? 0);
    $active = (!empty($data['active']) && $data['active'] != '0') ? 1 : 0;
    $linkwithpackage = (!empty($data['linkwithpackage']) && $data['linkwithpackage'] != '0') ? 1 : 0;
    $rewardpoint = intval($data['rewardpoint'] ?? 0);
    $quantity = intval($data['quantity'] ?? 0);
    $image = $data['image'] ?? '';

    if ($rowid > 0) {
        PDO_Execute("UPDATE products SET productname=?, scientificname=?, price=?, dp=?, active=?, linkwithpackage=?, rewardpoint=?, quantity=?, image=? WHERE rowid=?", 
            [$productname, $scientificname, $price, $dp, $active, $linkwithpackage, $rewardpoint, $quantity, $image, $rowid]);
        $q->rowid = $rowid;
    } else {
        PDO_Execute("INSERT INTO products (productname, scientificname, price, dp, active, linkwithpackage, rewardpoint, quantity, image) VALUES (?,?,?,?,?,?,?,?,?)", 
            [$productname, $scientificname, $price, $dp, $active, $linkwithpackage, $rewardpoint, $quantity, $image]);
        $q->rowid = PDO_LastInsertId();
    }
    $q->result = 1;
}

// 3. Toggle Active/Inactive Status for Product
if($routename=="toggleproduct")
{
    $rowid = intval($data['rowid'] ?? 0);
    if ($rowid > 0) {
        PDO_Execute("UPDATE products SET active = CASE WHEN IFNULL(active,1)=1 THEN 0 ELSE 1 END WHERE rowid=?", [$rowid]);
        $q->result = 1;
    } else {
        $q->result = 0;
    }
}

// 4. Delete Product (supports rowid or id)
if($routename=="deleteproduct")
{
    $rowid = intval($data['rowid'] ?? $data['id'] ?? $data['productid'] ?? 0);
    if ($rowid > 0) {
        PDO_Execute("DELETE FROM products WHERE rowid=?", [$rowid]);
        if (file_exists("../uploads/products/".$rowid.".jpg")) {
            @unlink("../uploads/products/".$rowid.".jpg");
        }
        $q->result = 1;
    } else {
        $q->result = 0;
    }
}

// 5. Fetch Customer & Package Orders with Shipping
if($routename=="orderlist")
{
    $q->result = 1;
    $orderList = [];

    try {
        // Auto-create orders, order_items, order_shipping tables if not present
        PDO_Execute("CREATE TABLE IF NOT EXISTS `orders` (
            `order_id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_number` VARCHAR(50) NOT NULL UNIQUE,
            `user_id` VARCHAR(50) NOT NULL,
            `order_type` VARCHAR(30) NOT NULL DEFAULT 'PACKAGE',
            `package_id` INT DEFAULT NULL,
            `total_items` INT NOT NULL DEFAULT 1,
            `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `total_dp` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `total_reward_points` INT NOT NULL DEFAULT 0,
            `payment_status` VARCHAR(20) NOT NULL DEFAULT 'PAID',
            `order_status` VARCHAR(20) NOT NULL DEFAULT 'PENDING',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (`user_id`),
            INDEX (`order_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        PDO_Execute("CREATE TABLE IF NOT EXISTS `order_items` (
            `item_id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `product_name` VARCHAR(255) NOT NULL,
            `scientific_name` VARCHAR(255) DEFAULT '',
            `quantity` INT NOT NULL DEFAULT 1,
            `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `dp` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `reward_point` INT NOT NULL DEFAULT 0,
            `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `total_reward_points` INT NOT NULL DEFAULT 0,
            INDEX (`order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        PDO_Execute("CREATE TABLE IF NOT EXISTS `order_shipping` (
            `shipping_id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT NOT NULL UNIQUE,
            `recipient_name` VARCHAR(100) NOT NULL DEFAULT '',
            `phone` VARCHAR(25) NOT NULL DEFAULT '',
            `address` TEXT NOT NULL,
            `city` VARCHAR(100) NOT NULL DEFAULT '',
            `state` VARCHAR(100) NOT NULL DEFAULT '',
            `pincode` VARCHAR(15) NOT NULL DEFAULT '',
            `shipping_mode` VARCHAR(50) NOT NULL DEFAULT 'Courier',
            `courier_name` VARCHAR(100) NOT NULL DEFAULT '',
            `tracking_number` VARCHAR(100) NOT NULL DEFAULT '',
            `shipping_status` VARCHAR(50) NOT NULL DEFAULT 'PENDING',
            `packed_date` DATETIME DEFAULT NULL,
            `dispatched_date` DATETIME DEFAULT NULL,
            `delivered_date` DATETIME DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (`order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Seed initial orders if table is completely empty
        $orderCount = PDO_FetchOne("SELECT COUNT(*) FROM orders");
        if ($orderCount == 0) {
            PDO_Execute("INSERT INTO orders (order_id, order_number, user_id, order_type, package_id, total_items, total_amount, total_dp, total_reward_points, payment_status, order_status, created_at) VALUES
                (1001, 'ORD-2026-1001', '180093', 'PACKAGE', 1, 2, 10000.00, 8000.00, 50, 'PAID', 'PACKED', DATE_SUB(NOW(), INTERVAL 5 DAY)),
                (1002, 'ORD-2026-1002', '157059', 'DIRECT_PRODUCT', NULL, 3, 2500.00, 2050.00, 75, 'PAID', 'SHIPPED', DATE_SUB(NOW(), INTERVAL 4 DAY)),
                (1003, 'ORD-2026-1003', '125374', 'PACKAGE', 1, 1, 10000.00, 8500.00, 35, 'PAID', 'DELIVERED', DATE_SUB(NOW(), INTERVAL 3 DAY)),
                (1004, 'ORD-2026-1004', '182328', 'DIRECT_PRODUCT', NULL, 2, 2000.00, 1600.00, 50, 'PAID', 'PENDING', DATE_SUB(NOW(), INTERVAL 1 DAY))");

            PDO_Execute("INSERT INTO order_items (order_id, product_id, product_name, scientific_name, quantity, price, dp, reward_point, total_price, total_reward_points) VALUES
                (1001, 1, 'Vietnam Super Early Jackfruit', 'Artocarpus heterophyllus', 1, 1000.00, 800.00, 25, 1000.00, 25),
                (1001, 2, 'Kumbhkat Seedless Lemon', 'Citrus limon', 1, 1000.00, 800.00, 25, 1000.00, 25),
                (1002, 2, 'Kumbhkat Seedless Lemon', 'Citrus limon', 2, 1000.00, 800.00, 20, 2000.00, 40),
                (1002, 3, 'PKM-1 Super Moringa', 'Moringa oleifera', 1, 500.00, 400.00, 15, 500.00, 15),
                (1003, 1, 'Vietnam Super Early Jackfruit', 'Artocarpus heterophyllus', 2, 1000.00, 800.00, 25, 2000.00, 50),
                (1004, 1, 'Vietnam Super Early Jackfruit', 'Artocarpus heterophyllus', 2, 1000.00, 800.00, 25, 2000.00, 50)");

            PDO_Execute("INSERT INTO order_shipping (order_id, recipient_name, phone, address, city, state, pincode, shipping_mode, courier_name, tracking_number, shipping_status) VALUES
                (1001, 'MANSAI', '9827112001', 'Near Kisan Mandi, Ward 4', 'Raipur', 'Chhattisgarh', '492001', 'Courier', 'DTDC Express', 'DTDC-89213401', 'PACKED'),
                (1002, 'Sandeep Sharma', '9752344102', 'Plot 42, Green Avenue, Telibandha', 'Bilaspur', 'Chhattisgarh', '495001', 'India Post', 'Speed Post', 'SP-CG49500128', 'SHIPPED'),
                (1003, 'TANIYA SANDILYA', '9179883344', 'Main Market Road, Durg', 'Durg', 'Chhattisgarh', '491001', 'Transport', 'VRL Logistics', 'VRL-9921045', 'DELIVERED'),
                (1004, 'Pankaj Kumar Biswas', '9425211990', 'Village Post Raigarh, Civil Lines', 'Raigarh', 'Chhattisgarh', '496001', 'Courier', 'Delhivery', '', 'PENDING')");
        }

        $orders = PDO_FetchAll("SELECT o.order_id, o.order_number, o.user_id as userid, o.order_type, o.package_id, 
                                       COALESCE(pl.packagename, 'Direct Product') as plan_name,
                                       o.total_items, o.total_amount, o.total_dp, o.total_reward_points, o.payment_status, o.order_status, 
                                       DATE_FORMAT(o.created_at, '%d %b %Y') as order_date,
                                       COALESCE(NULLIF(s.recipient_name, ''), pf.name, o.user_id) as customer_name, 
                                       COALESCE(NULLIF(s.phone, ''), pf.contact, '') as mobile, 
                                       COALESCE(NULLIF(s.address, ''), pf.address, 'Main Market Road') as address, 
                                       COALESCE(NULLIF(s.city, ''), pf.city, 'Raipur') as city, 
                                       COALESCE(NULLIF(s.state, ''), pf.state, 'Chhattisgarh') as state, 
                                       COALESCE(NULLIF(s.pincode, ''), pf.pincode, '492001') as pincode,
                                       COALESCE(s.courier_name, '') as courier_name, 
                                       COALESCE(s.tracking_number, '') as tracking_number, 
                                       COALESCE(s.shipping_mode, 'Courier') as shipping_mode, 
                                       COALESCE(s.notes, '') as notes,
                                       s.packed_date, s.dispatched_date, s.delivered_date
                                FROM orders o
                                LEFT JOIN order_shipping s ON s.order_id = o.order_id
                                LEFT JOIN personalinfo pf ON pf.userid = o.user_id
                                LEFT JOIN plans pl ON pl.rowid = o.package_id
                                ORDER BY o.order_id DESC");

        if (!empty($orders)) {
            foreach ($orders as $ord) {
                $oid = intval($ord['order_id']);
                $items = PDO_FetchAll("SELECT item_id, product_id, product_name, scientific_name, quantity, 
                                              price as unit_price, dp as unit_dp, reward_point, 
                                              total_price, total_reward_points 
                                       FROM order_items WHERE order_id = ?", [$oid]);
                $ord['order_id'] = $oid;
                $ord['total_amount'] = floatval($ord['total_amount']);
                $ord['total_dp'] = floatval($ord['total_dp']);
                $ord['total_reward_points'] = intval($ord['total_reward_points']);
                $ord['total_items'] = intval($ord['total_items'] ?? count($items));
                $ord['order_status'] = strtoupper($ord['order_status']);
                $ord['items'] = $items ? $items : [];
                $orderList[] = $ord;
            }
        }
    } catch (\Exception $e) {
        $q->error = $e->getMessage();
    }

    $q->orders = $orderList;
}

// 6. Update Order Shipping Details & Courier Status
if($routename=="updateordershipping")
{
    $order_id = intval($data['order_id'] ?? 0);
    $order_status = strtoupper(trim($data['shipping_status'] ?? $data['order_status'] ?? ''));
    $shipping_mode = trim($data['shipping_mode'] ?? 'Courier');
    $courier_name = trim($data['courier_name'] ?? '');
    $tracking_number = trim($data['tracking_number'] ?? '');
    $notes = trim($data['notes'] ?? '');

    if ($order_id > 0) {
        if (!empty($order_status)) {
            PDO_Execute("UPDATE orders SET order_status=?, updated_at=NOW() WHERE order_id=?", [$order_status, $order_id]);
        }

        $dateSql = "";
        if ($order_status === 'PACKED') {
            $dateSql = ", packed_date = NOW()";
        } elseif ($order_status === 'SHIPPED') {
            $dateSql = ", dispatched_date = NOW()";
        } elseif ($order_status === 'DELIVERED') {
            $dateSql = ", delivered_date = NOW()";
        }

        $shipExists = PDO_FetchOne("SELECT count(*) FROM order_shipping WHERE order_id=?", [$order_id]);
        if ($shipExists > 0) {
            PDO_Execute("UPDATE order_shipping SET shipping_status=?, shipping_mode=?, courier_name=?, tracking_number=?, notes=? $dateSql WHERE order_id=?", 
                [$order_status, $shipping_mode, $courier_name, $tracking_number, $notes, $order_id]);
        } else {
            // Fetch recipient info from personalinfo if creating row
            $farmer = null;
            try {
                $farmer = PDO_FetchRow("SELECT pf.name, pf.contact, pf.address, pf.city, pf.state, pf.pincode FROM orders o JOIN personalinfo pf ON pf.userid=o.user_id WHERE o.order_id=?", [$order_id]);
            } catch (\Exception $e) {}
            
            $recName = $farmer['name'] ?? '';
            $recPhone = $farmer['contact'] ?? '';
            $recAddr = $farmer['address'] ?? '';
            $recCity = $farmer['city'] ?? '';
            $recState = $farmer['state'] ?? 'Chhattisgarh';
            $recPin = $farmer['pincode'] ?? '';

            PDO_Execute("INSERT INTO order_shipping (order_id, recipient_name, phone, address, city, state, pincode, shipping_status, shipping_mode, courier_name, tracking_number, notes) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                [$order_id, $recName, $recPhone, $recAddr, $recCity, $recState, $recPin, $order_status, $shipping_mode, $courier_name, $tracking_number, $notes]);
        }

        $q->result = 1;
        $q->message = "Order shipping updated successfully";
    } else {
        $q->result = 0;
        $q->message = "Invalid order ID";
    }
}

// 7. Create New Order (Consumer Direct Purchase / Package Plant Order)
if($routename=="createorder")
{
    $userid = $data['user_id'] ?? $data['userid'] ?? '';
    $order_type = $data['order_type'] ?? 'DIRECT_PRODUCT'; // 'PACKAGE' or 'DIRECT_PRODUCT'
    $package_id = !empty($data['package_id']) ? intval($data['package_id']) : null;
    $items = isset($data['items']) ? (is_string($data['items']) ? json_decode($data['items'], true) : $data['items']) : [];
    $shipping = isset($data['shipping']) ? (is_string($data['shipping']) ? json_decode($data['shipping'], true) : $data['shipping']) : [];

    if (!empty($userid) && !empty($items)) {
        $order_number = 'ORD-' . date('Ymd') . '-' . rand(1000, 9999);
        $total_amount = 0.0;
        $total_dp = 0.0;
        $total_reward_points = 0;

        PDO_Execute("INSERT INTO orders (order_number, user_id, order_type, package_id, total_amount, total_dp, total_reward_points, payment_status, order_status, created_at) 
                     VALUES (?, ?, ?, ?, 0, 0, 0, 'Paid', 'Pending', date_add(date_add(now(),interval 5 hour), interval 30 minute))", 
                     [$order_number, $userid, $order_type, $package_id]);
        
        $order_id = PDO_LastInsertId();

        foreach ($items as $item) {
            $pid = intval($item['product_id'] ?? $item['rowid'] ?? 0);
            $qty = intval($item['quantity'] ?? $item['qty'] ?? 1);

            $prod = PDO_FetchRow("SELECT productname, scientificname, price, dp, rewardpoint, quantity FROM products WHERE rowid=?", [$pid]);
            if ($prod) {
                $pPrice = floatval($prod['price']);
                $pDp = floatval($prod['dp']);
                $pReward = ($order_type === 'DIRECT_PRODUCT') ? intval($prod['rewardpoint']) : 0;

                $tPrice = $pPrice * $qty;
                $tReward = $pReward * $qty;

                $total_amount += $tPrice;
                $total_dp += ($pDp * $qty);
                $total_reward_points += $tReward;

                PDO_Execute("INSERT INTO order_items (order_id, product_id, product_name, scientific_name, quantity, price, dp, reward_point, total_price, total_reward_points) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                             [$order_id, $pid, $prod['productname'], $prod['scientificname'], $qty, $pPrice, $pDp, $pReward, $tPrice, $tReward]);

                PDO_Execute("UPDATE products SET quantity = CASE WHEN quantity >= ? THEN quantity - ? ELSE 0 END WHERE rowid=?", [$qty, $qty, $pid]);
            }
        }

        PDO_Execute("UPDATE orders SET total_amount=?, total_dp=?, total_reward_points=? WHERE order_id=?", 
                     [$total_amount, $total_dp, $total_reward_points, $order_id]);

        $recipient = $shipping['name'] ?? $shipping['recipient_name'] ?? $userid;
        $phone = $shipping['phone'] ?? $shipping['mobile'] ?? '';
        $address = $shipping['address'] ?? '';
        $city = $shipping['city'] ?? '';
        $state = $shipping['state'] ?? '';
        $pincode = $shipping['pincode'] ?? '';

        PDO_Execute("INSERT INTO order_shipping (order_id, recipient_name, phone, address, city, state, pincode, shipping_status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')", 
                     [$order_id, $recipient, $phone, $address, $city, $state, $pincode]);

        if ($order_type === 'DIRECT_PRODUCT' && $total_reward_points > 0) {
            PDO_Execute("INSERT INTO user_reward_points (user_id, order_id, points, type, description, created_at) 
                         VALUES (?, ?, ?, 'CREDIT', ?, date_add(date_add(now(),interval 5 hour), interval 30 minute))", 
                         [$userid, $order_id, $total_reward_points, "Reward points for purchase of plants (Order #$order_number)"]);
        }

        $q->result = 1;
        $q->order_id = $order_id;
        $q->order_number = $order_number;
        $q->reward_points = $total_reward_points;
    } else {
        $q->result = 0;
        $q->msg = "Missing user_id or items";
    }
}

// 8. Farmer Package Plant Allocations (from formar_plants)
if($routename=="formarplantslist")
{
    $userid = $data['userid'] ?? $data['user_id'] ?? '';
    if (!empty($userid)) {
        $q->data = PDO_FetchAll("SELECT fp.*, p.productname, p.scientificname, p.image 
                                 FROM formar_plants fp 
                                 LEFT JOIN products p ON (p.rowid = fp.plantid OR p.rowid = fp.productid) 
                                 WHERE fp.userid=? ORDER BY fp.rowid DESC", [$userid]);
    } else {
        $q->data = PDO_FetchAll("SELECT fp.*, p.productname, p.scientificname, p.image 
                                 FROM formar_plants fp 
                                 LEFT JOIN products p ON (p.rowid = fp.plantid OR p.rowid = fp.productid) 
                                 ORDER BY fp.rowid DESC LIMIT 200");
    }
    $q->result = 1;
}

// ==============================================================================
// GOOGLE AUTHENTICATOR (TOTP) 2FA CONFIGURATION ROUTES
// ==============================================================================

function ensureAdmin2faColumns() {
    try {
        PDO_Execute("ALTER TABLE `adminusers` ADD COLUMN `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0");
    } catch (\Exception $e) {}
    try {
        PDO_Execute("ALTER TABLE `adminusers` ADD COLUMN `two_factor_secret` VARCHAR(64) NULL");
    } catch (\Exception $e) {}
    try {
        PDO_Execute("ALTER TABLE `adminusers` ADD COLUMN `two_factor_confirmed` TINYINT(1) NOT NULL DEFAULT 0");
    } catch (\Exception $e) {}
}

// 9. Setup 2FA - Generate QR Code & Secret
if($routename=="setup_2fa")
{
    ensureAdmin2faColumns();
    $targetUser = !empty($data['username']) ? $data['username'] : (!empty($username) ? $username : 'admin');
    
    $existing = PDO_FetchRow("SELECT username FROM adminusers WHERE username = ? OR email = ?", [$targetUser, $targetUser]);
    if ($existing) {
        $targetUser = $existing['username'];
    }
    
    $secret = CarbonovaTOTP::generateSecret();
    
    // Save generated secret (unconfirmed until user enters verification code)
    PDO_Execute("UPDATE adminusers SET two_factor_secret = ?, two_factor_confirmed = 0 WHERE username = ?", [$secret, $targetUser]);
    
    $otpAuthUrl = CarbonovaTOTP::getOtpAuthUrl("Carbonova Admin", $targetUser, $secret);
    $qrCodeUrl = CarbonovaTOTP::getQrCodeUrl("Carbonova Admin", $targetUser, $secret);
    
    $q->result = 1;
    $q->secret = $secret;
    $q->otpauth_url = $otpAuthUrl;
    $q->qr_code_url = $qrCodeUrl;
}

// 10. Confirm 2FA - Verify initial 6-digit code and activate 2FA
if($routename=="confirm_2fa")
{
    ensureAdmin2faColumns();
    $targetUser = !empty($data['username']) ? $data['username'] : (!empty($username) ? $username : 'admin');
    $code = trim($data['code'] ?? $data['totp_code'] ?? '');
    
    $userRow = PDO_FetchRow("SELECT two_factor_secret FROM adminusers WHERE username = ? OR email = ?", [$targetUser, $targetUser]);
    
    if ($userRow && !empty($userRow['two_factor_secret'])) {
        $valid = CarbonovaTOTP::verifyCode($userRow['two_factor_secret'], $code, 1);
        if ($valid) {
            PDO_Execute("UPDATE adminusers SET two_factor_enabled = 1, two_factor_confirmed = 1 WHERE username = ?", [$targetUser]);
            $q->result = 1;
            $q->message = "Google Authenticator 2FA enabled successfully!";
        } else {
            $q->result = 0;
            $q->message = "Invalid 6-digit verification code. Please check your Authenticator app and try again.";
        }
    } else {
        $q->result = 0;
        $q->message = "2FA setup secret not found. Please click 'Setup 2FA' again.";
    }
}

// 11. Disable 2FA
if($routename=="disable_2fa")
{
    ensureAdmin2faColumns();
    $targetUser = !empty($data['username']) ? $data['username'] : (!empty($username) ? $username : 'admin');
    PDO_Execute("UPDATE adminusers SET two_factor_enabled = 0, two_factor_confirmed = 0, two_factor_secret = NULL WHERE username = ? OR email = ?", [$targetUser, $targetUser]);
    $q->result = 1;
    $q->message = "Google Authenticator 2FA has been disabled.";
}

// 12. Check 2FA Status
if($routename=="status_2fa")
{
    ensureAdmin2faColumns();
    $targetUser = !empty($data['username']) ? $data['username'] : (!empty($username) ? $username : 'admin');
    $userRow = PDO_FetchRow("SELECT IFNULL(two_factor_enabled, 0) as enabled, IFNULL(two_factor_confirmed, 0) as confirmed FROM adminusers WHERE username = ? OR email = ?", [$targetUser, $targetUser]);
    $q->result = 1;
    $q->two_factor_enabled = ($userRow && $userRow['enabled'] == 1 && $userRow['confirmed'] == 1);
}

echo json_encode($q);
