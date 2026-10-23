<?php
class Memory {
    private $db, $uid;
    public function __construct($db, $uid) { $this->db=$db; $this->uid=$uid; }
    public function recall($q) {
        try {
            $stmt = $this->db->prepare("SELECT content FROM cortex WHERE user_id=? ORDER BY id DESC LIMIT 5");
            $stmt->execute([$this->uid]);
            $out=""; while($r=$stmt->fetch(PDO::FETCH_ASSOC)) $out.="- ".$r['content']."\n";
            return $out;
        } catch(Exception $e) { return ""; }
    }
    public function memorize($c) {
        try { $this->db->prepare("INSERT INTO cortex (user_id, content) VALUES (?, ?)")->execute([$this->uid, $c]); } catch(Exception $e) {}
    }
}
