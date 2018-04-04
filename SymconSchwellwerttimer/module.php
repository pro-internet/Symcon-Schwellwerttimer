<?
define("PHP_INT_MIN",-2147483648);

class SchwellwertTimer extends IPSModule {

	//branch = master

	private $nachlaufzeitAbgelaufen = false;

    public function Create()
	{
		// Diese Zeile nicht löschen.
		parent::Create();

		if(@$this->RegisterPropertyInteger("Sensor") !== false)
		{
			$this->RegisterPropertyInteger("Unit", 4);
			$this->RegisterPropertyInteger("Unit2", 4);
			$this->RegisterPropertyInteger("Unit3", 4);
			$this->RegisterPropertyInteger("Sensor", 0);
			$this->RegisterPropertyInteger("Sensor2", 0);
			$this->RegisterPropertyInteger("Sensor3", 0);
			$this->RegisterPropertyString("valueOff", "0");
			$this->RegisterPropertyString("valueOn", "1");
			$this->RegisterPropertyInteger("instance", $this->InstanceID);
		}

		if(@$this->RegisterPropertyInteger("scriptOn") !== false)
		{
			$this->RegisterPropertyInteger("scriptOn", 0);
			$this->RegisterPropertyInteger("scriptOff", 0);
		}

		//SetValueScript erstellen
		if(@IPS_GetObjectIDByIdent("SetValueScript", $this->InstanceID) === false)
		{
			$vid = IPS_CreateScript(0 /* PHP Script */);
			IPS_SetParent($vid, $this->InstanceID);
			IPS_SetName($vid, "SetValue");
			IPS_SetIdent($vid, "SetValueScript");
			IPS_SetHidden($vid, true);
			IPS_SetScriptContent($vid, "<?

if (\$IPS_SENDER == \"WebFront\")
{
    SetValue(\$_IPS['VARIABLE'], \$_IPS['VALUE']);
}

?>");
		}

		//Delay Variable erstellen DelayVar
		if(@IPS_GetObjectIDByIdent("DelayVar", $this->InstanceID) === false)
		{
			$svid = IPS_GetObjectIDByIdent("SetValueScript", $this->InstanceID);
			$vid = IPS_CreateVariable(1 /* Integer */);
			IPS_SetParent($vid, $this->InstanceID);
			IPS_SetName($vid, "Verzögerung");
			IPS_SetIcon($vid, "Clock");
			IPS_SetIdent($vid, "DelayVar");
			IPS_SetPosition($vid,2);
			if(IPS_VariableProfileExists("~UnixTimestampTime"))
			{
				IPS_SetVariableCustomProfile($vid,"~UnixTimestampTime");
			}
			else
			{
				if(IPS_VariableProfileExists("SWT.Delay"))
				{
					IPS_SetVariableCustomProfile($vid,"SWT.Delay");
				}
				else
				{
					IPS_CreateVariableProfile("SWT.Delay", 1);
					IPS_SetVariableProfileValues("SWT.Delay", 0, 600, 1);
					IPS_SetVariableProfileText("SWT.Delay",""," Sek.");
					//IPS_SetVariableProfileIcon("SWT.Delay", "");

					IPS_SetVariableCustomProfile($vid,"SWT.Delay");
				}
			}
			IPS_SetVariableCustomAction($vid,$svid);
			SetValue($vid,1);
		}

		//Status Variable erstellen
		if(@IPS_GetObjectIDByIdent("Status", $this->InstanceID) === false)
		{
			$svid = IPS_GetObjectIDByIdent("SetValueScript", $this->InstanceID);
			$vid = IPS_CreateVariable(0 /* Boolean */);
			IPS_SetParent($vid, $this->InstanceID);
			IPS_SetName($vid, "Status");
			IPS_SetIdent($vid, "Status");
			IPS_SetPosition($vid,0);
			if(IPS_VariableProfileExists("Switch"))
			{
				IPS_SetVariableCustomProfile($vid,"Switch");
			}
			else
			{
				IPS_CreateVariableProfile("Switch",0);
				IPS_SetVariableProfileValues("Switch",0,1,1);
				IPS_SetVariableProfileAssociation("Switch",0,"Aus","",-1);
				IPS_SetVariableProfileAssociation("Switch",1,"An","", 0x8000FF);
				IPS_SetVariableProfileIcon("Switch","Power");

				IPS_SetVariableCustomProfile($vid,"Switch");
			}
			IPS_SetVariableCustomAction($vid,$svid);
		}

		//Status OnChange event
		if(@IPS_GetObjectIDByIdent("StatusOnChange",$this->InstanceID) === false)
		{
			$vid = IPS_GetObjectIDByIdent("Status", $this->InstanceID);
			$eid = IPS_CreateEvent(0);
			IPS_SetEventTrigger($eid,1,$vid);
			IPS_SetParent($eid,$this->InstanceID);
			IPS_SetName($eid,"Status OnChange");
			IPS_SetIdent($eid,"StatusOnChange");
			IPS_SetEventActive($eid, true);
			IPS_SetEventScript($eid, "SWT_statusOnChange(". $this->InstanceID .");");
		}

		//Nachlaufzeit Variable erstellen
		if(@IPS_GetObjectIDByIdent("NachlaufzeitVariable",$this->InstanceID) === false)
		{
			$svid = IPS_GetObjectIDByIdent("SetValueScript", $this->InstanceID);
			$vid = IPS_CreateVariable(1 /* Integer */);
			IPS_SetParent($vid, $this->InstanceID);
			IPS_SetName($vid, "Nachlauf");
			IPS_SetIdent($vid, "NachlaufzeitVariable");
			IPS_SetIcon($vid, "Clock");
			IPS_SetPosition($vid, 3);
			if(IPS_VariableProfileExists("~UnixTimestampTime"))
			{
				IPS_SetVariableCustomProfile($vid,"~UnixTimestampTime");
			}
			else
			{
				if(IPS_VariableProfileExists("SWT.Seconds"))
				{
					IPS_SetVariableCustomProfile($vid,"SWT.Seconds");
				}
				else
				{
					IPS_CreateVariableProfile("SWT.Seconds", 1);
					IPS_SetVariableProfileValues("SWT.Seconds", 0, 7200, 1);
					IPS_SetVariableProfileText("SWT.Seconds",""," Sek.");
					//IPS_SetVariableProfileIcon("SWT.Seconds", "");

					IPS_SetVariableCustomProfile($vid,"SWT.Seconds");
				}
			}
			IPS_SetVariableCustomAction($vid,$svid);
			SetValue($vid,1);
		}

		//Nachlaufzeit OnChange
		if(@IPS_GetObjectIDByIdent("NachlaufzeitOnChange",$this->InstanceID) === false)
		{
			$eid = IPS_CreateEvent(0);
			IPS_SetEventTrigger($eid,1,$vid);
			IPS_SetParent($eid,$this->InstanceID);
			IPS_SetName($eid,"Nachlaufzeit OnChange");
			IPS_SetIdent($eid,"NachlaufzeitOnChange");
			IPS_SetEventActive($eid, true);
			IPS_SetEventScript($eid, "SWT_createDelayTimer(". $this->InstanceID .");");
		}

		//Automatikbutton (ein und ausschalten des moduls)
		if(@IPS_GetObjectIDByIdent("Automatik",$this->InstanceID) === false)
		{
			$svid = IPS_GetObjectIDByIdent("SetValueScript", $this->InstanceID);
			$vid = IPS_CreateVariable(0 /* Boolean */);
			IPS_SetParent($vid, $this->InstanceID);
			IPS_SetName($vid, "Automatik");
			IPS_SetIdent($vid, "Automatik");
			IPS_SetPosition($vid,0);
			//Profil
			if(!IPS_VariableProfileExists("Switch"))
			{
        IPS_CreateVariableProfile("Switch",0);
				IPS_SetVariableProfileValues("Switch",0,1,1);
				IPS_SetVariableProfileAssociation("Switch",0,"Aus","",-1);
				IPS_SetVariableProfileAssociation("Switch",1,"An","", 0x8000FF);
				IPS_SetVariableProfileIcon("Switch","Power");

				IPS_SetVariableCustomProfile($vid,"Switch");
			}
			IPS_SetVariableCustomProfile($vid,"Switch");

			IPS_SetVariableCustomAction($vid,$svid);
			SetValue($vid,false);
		}

		//Automatik OnChange to off event
		if(@IPS_GetObjectIDByIdent("TriggerOnChange",$this->InstanceID) === false)
		{
			$vid = IPS_GetObjectIDByIdent("Automatik",$this->InstanceID);
			$eid = IPS_CreateEvent(0);
			IPS_SetEventTrigger($eid,1,$vid);
			IPS_SetEventTriggerValue($eid,false);
			IPS_SetParent($eid,$this->InstanceID);
			IPS_SetName($eid,"Trigger OnChange");
			IPS_SetIdent($eid,"TriggerOnChange");
			IPS_SetEventActive($eid, true);
			IPS_SetEventScript($eid, "SWT_turnOffEverything(". $this->InstanceID .");");
		}
    }

	//Schwellwert Variable erstellen
	private function CreateLimitVariable($type,$num = "")
	{
		if(@IPS_GetObjectIDByIdent("limit$num", $this->InstanceID) === false)
		{
			//variable
			$svid = IPS_GetObjectIDByIdent("SetValueScript", $this->InstanceID);
			$vid = IPS_CreateVariable($type);
			IPS_SetParent($vid, $this->InstanceID);
			if($num == "")
			{
				IPS_SetName($vid, "Schwellwert1");
			}
			else
			{
				IPS_SetName($vid, "Schwellwert$num");
			}

			IPS_SetIdent($vid, "limit$num");
			IPS_SetPosition($vid, 1);
			IPS_SetVariableCustomAction($vid, $svid);

			if(@IPS_GetObjectIDByIdent("onChangeSchwell$num", $this->InstanceID) === false)
			{
				//onchange event
				$eid = IPS_CreateEvent(0 /* ausgelößt */);
				IPS_SetEventTrigger($eid,1,$vid);
				IPS_SetEventScript($eid,"SWT_createDelayTimer(". $this->InstanceID .");");
				IPS_SetIdent($eid,"onChangeSchwell$num");
				IPS_SetName($eid,"onChange Schwellwert$num");
				IPS_SetParent($eid, $this->InstanceID);
				IPS_SetHidden($eid,true);
				IPS_SetEventActive($eid, true);
			}
			else
			{
				$eid = IPS_GetObjectIDByIdent("onChangeSchwell$num", $this->InstanceID);
				IPS_DeleteEvent($eid);
				//onchange event
				$eid = IPS_CreateEvent(0 /* ausgelößt */);
				IPS_SetEventTrigger($eid,1,$vid);
				IPS_SetEventScript($eid,"SWT_refreshStatus(". $this->InstanceID .");");
				IPS_SetIdent($eid,"onChangeSchwell$num");
				IPS_SetName($eid,"onChange Schwellwert$num");
				IPS_SetParent($eid, $this->InstanceID);
				IPS_SetHidden($eid,true);
				IPS_SetEventActive($eid, true);
			}


			return $vid;
		}
		else
		{
			$vid = IPS_GetObjectIDByIdent("limit$num", $this->InstanceID);
			if(IPS_GetVariable($vid)['VariableType'] != $type)
			{
				if(@IPS_GetObjectIDByIdent("onChangeSchwell$num", $this->InstanceID !== false))
				{
					$eid = IPS_GetObjectIDByIdent("onChangeSchwell$num",$this->InstanceID);
					IPS_DeleteEvent($eid);
				}
				IPS_DeleteVariable($vid);
				$vid = $this->CreateLimitVariable($type,$num);
				return $vid;
			}
			return $vid;
		}
	}

	//alle Werte, Timer, etc. zurrücksetzen
	public function turnOffEverything()
	{
		if(@IPS_GetObjectIDByIdent("DelayTimer", $this->InstanceID) !== false)
		{
			$eid = IPS_GetObjectIDByIdent("DelayTimer", $this->InstanceID);
			IPS_SetEventActive($eid, false);
			IPS_DeleteEvent($eid);
		}

		if(@IPS_GetObjectIDByIdent("Status", $this->InstanceID) !== false)
		{
			$vid = IPS_GetObjectIDByIdent("Status", $this->InstanceID);
			SetValue($vid, false);
		}

		if(@IPS_GetObjectIDByIdent("NachlaufTimer", $this->InstanceID) !== false)
		{
			$eid = IPS_GetObjectIDByIdent("NachlaufTimer", $this->InstanceID);
			IPS_SetEventActive($eid, false);
			IPS_DeleteEvent($eid);
		}
	}
		///////////////////
		// Profilbereich //
		///////////////////

		private function createVariableProfile($num = "")
		{
			switch($this->ReadPropertyInteger("Unit$num"))
			{
				case(1 /*°C*/):
					$vid = $this->CreateLimitVariable(1 /* integer */,$num);

					if(!IPS_VariableProfileExists("SWT.DegreeCelsius"))
					{
					IPS_CreateVariableProfile("SWT.DegreeCelsius", 1);
					IPS_SetVariableProfileValues("SWT.DegreeCelsius", 0, 40, 1);
					IPS_SetVariableProfileText("SWT.DegreeCelsius", "", "°C");
					//IPS_SetVariableProfileIcon("SWT.DegreeCelsius", "");
					}
					IPS_SetVariableCustomProfile($vid, "SWT.DegreeCelsius");
					break;
				case(2 /*°F*/):
					$vid = $this->CreateLimitVariable(1 /* integer */,$num);

					if(!IPS_VariableProfileExists("SWT.DegreeFahrenheit"))
					{
					IPS_CreateVariableProfile("SWT.DegreeFahrenheit", 1);
					IPS_SetVariableProfileValues("SWT.DegreeFahrenheit", 0, 105, 1);
					IPS_SetVariableProfileText("SWT.DegreeFahrenheit", "", "°F");
					//IPS_SetVariableProfileIcon("SWT.DegreeFahrenheit", "");
					}
					IPS_SetVariableCustomProfile($vid, "SWT.DegreeFahrenheit");
					break;
				case(3 /*Lux*/):
					$vid = $this->CreateLimitVariable(1 /* integer */,$num);

					if(!IPS_VariableProfileExists("SWT.Lux"))
					{
					IPS_CreateVariableProfile("SWT.Lux", 1);
					IPS_SetVariableProfileValues("SWT.Lux", 0, 80000, 1000);
					IPS_SetVariableProfileText("SWT.Lux", "", " lx");
					//IPS_SetVariableProfileIcon("SWT.Lux", "");
					}
					IPS_SetVariableCustomProfile($vid, "SWT.Lux");
					break;
				case(4 /*same as sensor*/):
					$sensorID = $this->ReadPropertyInteger("Sensor$num");
					// Uberprüft die validität der Variable
					if($sensorID >= 10000)
					{
						$systemProfile = IPS_GetVariable($sensorID)['VariableProfile'];
						$customProfile = IPS_GetVariable($sensorID)['VariableCustomProfile'];
						if($customProfile != "")
						{
							$type = IPS_GetVariable($sensorID)['VariableType'];
							$vid = $this->CreateLimitVariable($type,$num);
							IPS_SetVariableCustomProfile($vid, $customProfile);
						}
						else if($systemProfile != "")
						{
							$type = IPS_GetVariable($sensorID)['VariableType'];
							$vid = $this->CreateLimitVariable($type,$num);
							IPS_SetVariableCustomProfile($vid, $systemProfile);
						}
						else
						{
							try
							{
								$error = "\nInvalid Variable Profile\n";
								if(gettype($customProfile) != "string")
								{
									$error .= 'Types detected: ' . gettype($customProfile);
									$error .= ', ' . gettype($systemProfile) . '\n';
									$error .= 'Type expected: "string"';
								}
								else
								{
									$error .= "→ Profile is empty";
								}
								throw new Exception($error);
							}
							catch (Exception $e)
							{
								echo 'Caught exception: ',  $e->getMessage(), "\n";
							}
						}
					}
					break;
				case(5 /*Watt*/):
					$vid = $this->CreateLimitVariable(1 /* integer */,$num);

					if(!IPS_VariableProfileExists("SWT.Watt"))
					{
					IPS_CreateVariableProfile("SWT.Watt", 1);
					IPS_SetVariableProfileValues("SWT.Watt", 0, 12000, 100);
					IPS_SetVariableProfileText("SWT.Watt", "", "W");
					//IPS_SetVariableProfileIcon("SWT.Watt", "");
					}
					IPS_SetVariableCustomProfile($vid, "SWT.Watt");
					break;
				default:
					$unit = $this->ReadPropertyInteger("Unit");
					try
					{

						$error = "\nInvalid Unit Index: $unit\n";
						$error .= "1: Degree (°C)\n";
						$error .= "2: Degree (°F)\n";
						$error .= "3: Lux (lx)\n";
						$error .= "4: Same as Sensor\n";
						$error .= "5: Watt";
						throw new Exception($error);

					}
					catch (Exception $e)
					{
						echo 'Caught exception: ',  $e->getMessage(), "\n";
					}
			}
		}

		private function createSensorEvent($num = "")
		{
			if(@IPS_GetObjectIDByIdent("onChangeSensor$num",$this->InstanceID) === false)
			{
				$vid = $this->ReadPropertyInteger("Sensor$num");

				$eid = IPS_CreateEvent(0 /* ausgelößt */);
				IPS_SetEventTrigger($eid,1,$vid);
				IPS_SetEventScript($eid,"SWT_createDelayTimer(". $this->InstanceID .");");
				IPS_SetIdent($eid,"onChangeSensor$num");
				IPS_SetName($eid,"onChange Sensor$num");
				IPS_SetParent($eid, $this->InstanceID);
				IPS_SetHidden($eid,true);
				IPS_SetEventActive($eid, true);
			}
			else
			{
				$eid = IPS_GetObjectIDByIdent("onChangeSensor$num",$this->InstanceID);
				$eidTriggerID = IPS_GetEvent($eid)['TriggerVariableID'];
				$newSensorID = $this->ReadPropertyInteger("Sensor$num");
				if($eidTriggerID != $newSensorID && $newSensorID >= 10000)
				{
					IPS_DeleteEvent($eid);
					$this->createSensorEvent($num);
				}
			}
		}

		// Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges()
		{
            // Diese Zeile nicht löschen
            parent::ApplyChanges();

			//onchange event Sensor
			$this->createSensorEvent();

			$vid = $this->ReadPropertyInteger("Sensor2");
			if($vid >= 10000)
			{
				$this->createSensorEvent("2");
			}
			$vid = $this->ReadPropertyInteger("Sensor3");
			if($vid >= 10000)
			{
				$this->createSensorEvent("3");
			}

			//Create on change event for automatik
			if(@IPS_GetObjectIDByIdent("AutomatikOnChange", $this->InstanceID) === false)
			{
				$automatikID = IPS_GetObjectIDByIdent("Automatik", $this->InstanceID);
				$eid = IPS_CreateEvent(0);
				IPS_SetEventTrigger($eid, 0, $automatikID);
				IPS_SetEventScript($eid, "if(GetValue($automatikID) === true) { 
					SWT_createDelayTimer(". $this->InstanceID ."); 
				}");
				IPS_SetParent($eid, $this->InstanceID);
				IPS_SetName($eid, "Automatik OnChange");
				IPS_SetIdent($eid, "AutomatikOnChange");
				IPS_SetEventActive($eid, true);
			}

			//Change Location of the targets folder
			if(@IPS_GetObjectIDByIdent("Targets", IPS_GetParent($this->InstanceID)) === false)
			{
				$dummyGUID = $this->GetModuleIDByName();
				$insID = IPS_CreateInstance($dummyGUID);
				IPS_SetName($insID, "Targets");
				IPS_SetParent($insID, IPS_GetParent($this->InstanceID));
				IPS_SetIdent($insID, "Targets");
			}
			else
			{
				$insID = IPS_GetObjectIDByIdent("Targets", IPS_GetParent($this->InstanceID));
			}

			if(@IPS_GetObjectIDByIdent("Targets", $this->InstanceID) !== false)
			{
				$cid = IPS_GetObjectIDByIdent("Targets", $this->InstanceID);
				foreach(IPS_GetChildrenIDs($cid) as $c)
				{
					$o = IPS_GetObject($c);
					$l = IPS_GetLink($c);
					$content = array_merge($o,$l);
					$content['ParentID'] = $insID;
					$this->CreateLink($content);
				}
				$this->Del($cid);
			}

			///////////////////
			// Profilbereich //
			///////////////////
			$this->createVariableProfile();
			if($this->ReadPropertyInteger("Sensor2") >= 10000)
			{
				$this->createVariableProfile("2");
			}
			else
			{
				//delete Schwellwert 2 var
				if(@IPS_GetObjectIDByIdent("limit2", $this->InstanceID) !== false)
				{
					$limitVar2 = IPS_GetObjectIDByIdent("limit2", $this->InstanceID);
					IPS_DeleteVariable($limitVar2);
				}
				//delete Sensor 2 Event
				if(@IPS_GetObjectIDByIdent("onChangeSensor2", $this->InstanceID) !== false)
				{
					$sensEvent2 = IPS_GetObjectIDByIdent("onChangeSensor2", $this->InstanceID);
					IPS_DeleteEvent($sensEvent2);
				}
				//delete Schwellwert 2 Event
				if(@IPS_GetObjectIDByIdent("onChangeSchwell2", $this->InstanceID) !== false)
				{
					$schwellEvent2 = IPS_GetObjectIDByIdent("onChangeSchwell2", $this->InstanceID);
					IPS_DeleteEvent($schwellEvent2);
				}
			}
			
			if($this->ReadPropertyInteger("Sensor3") >= 10000)
			{
				$this->createVariableProfile("3");
			}
			else
			{
				//delete Schwellwert 3 var
				if(@IPS_GetObjectIDByIdent("limit3", $this->InstanceID) !== false)
				{
					$limitVar3 = IPS_GetObjectIDByIdent("limit3", $this->InstanceID);
					IPS_DeleteVariable($limitVar3);
				}
				//delete Sensor 3 Event
				if(@IPS_GetObjectIDByIdent("onChangeSensor3", $this->InstanceID) !== false)
				{
					$sensEvent3 = IPS_GetObjectIDByIdent("onChangeSensor3", $this->InstanceID);
					IPS_DeleteEvent($sensEvent3);
				}
				//delete Schwellwert 3 Event
				if(@IPS_GetObjectIDByIdent("onChangeSchwell3", $this->InstanceID) !== false)
				{
					$schwellEvent3 = IPS_GetObjectIDByIdent("onChangeSchwell3", $this->InstanceID);
					IPS_DeleteEvent($schwellEvent3);
				}
			}

			//////////////////
			// Logikbereich //
			//////////////////

			//$tid = $this->RegisterTimer("Update", 1000 /*jede sekunde*/, "SWT_refreshStatus(". $this->InstanceID .");");
			$this->createDelayTimer();
		
		}

		/**
        * Die folgenden Funktionen stehen automatisch zur Verfügung, wenn das Modul über die "Module Control" eingefügt wurden.
        * Die Funktionen werden, mit dem selbst eingerichteten Prefix, in PHP und JSON-RPC wiefolgt zur Verfügung gestellt:
        *
        * ABC_MeineErsteEigeneFunktion($id);
        *
		*/
		protected function GetModuleIDByName($name = "Dummy Module")
		{
			$moduleList = IPS_GetModuleList();
			$GUID = ""; //init
			foreach($moduleList as $l)
			{
				if(IPS_GetModule($l)['ModuleName'] == $name)
				{
					$GUID = $l;
					break;
				}
			}
			return $GUID;
		}

		public function createDelayTimer()
		{
			$instance = $this->ReadPropertyInteger("instance");
			$automatik = IPS_GetObjectIDByIdent("Automatik",$instance);
			$automatik = GetValue($automatik);
			if($automatik)
			{
				$sid = $this->ReadPropertyInteger("Sensor");
				$sid2 = $this->ReadPropertyInteger("Sensor2");
				$sid3 = $this->ReadPropertyInteger("Sensor3");
				$lid = IPS_GetObjectIDByIdent("limit", $this->InstanceID);
				$lid2 = @IPS_GetObjectIDByIdent("limit2", $this->InstanceID);
				$lid2 = (int) $lid2;
				$lid3 = @IPS_GetObjectIDByIdent("limit3", $this->InstanceID);
				$lid3 = (int) $lid3;
				$statusID = IPS_GetObjectIDByIdent("Status", $this->InstanceID);
				$ntID = IPS_GetObjectIDByIdent("NachlaufzeitVariable", $this->InstanceID);

				//limits
				try
				{
					$limit = GetValue($lid);
				}
				catch(Exception $e)
				{
					echo 'can not get Value of Limit ' . $lid . '\n', $e->GetMessage(), '\n';
				}
				if($sid2 >= 10000)
					$limit2 = GetValue($lid2);
				else
					$limit2 = PHP_INT_MIN;
				if($sid3 >= 10000)
					$limit3 = GetValue($lid3);
				else
					$limit3 = PHP_INT_MIN;
				//sensors
				try
				{
					$sensor = GetValue($sid);
				}
				catch(Exception $e)
				{
					echo 'can not get Value of Sensor ' . $sid . '\n', $e->GetMessage(), '\n';
				}
				if($sid2 >= 10000)
					$sensor2 = GetValue($sid2);
				else
					$sensor2 = PHP_INT_MAX;
				if($sid3 >= 10000)
					$sensor3 = GetValue($sid3);
				else
					$sensor3 = PHP_INT_MAX;

				$nachlaufzeit = GetValue($ntID);
				//format timestamp
				if(IPS_VariableProfileExists("~UnixTimestampTime"))
				{
					$nachlaufzeit += 3600;
				}
				if($nachlaufzeit < 1) { $nachlaufzeit = 0.05; }
				if($limit < $sensor && $limit2 < $sensor2 && $limit3 < $sensor3) //Above limit
				{
					if(@IPS_GetObjectIDByIdent("DelayTimer", $this->InstanceID) === false)
					{
						$eid = IPS_CreateEvent(1 /*zyklisch*/);
						IPS_SetHidden($eid,true);
						IPS_SetName($eid, "Delay Timer");
						IPS_SetParent($eid, $this->InstanceID);
						IPS_SetIdent($eid, "DelayTimer");
						IPS_SetEventScript($eid, "SWT_refreshStatus(". $this->InstanceID .");");
						IPS_SetEventCyclicTimeFrom($eid, (int)date("H"), (int)date("i"), (int)date("s"));
					}
					else
					{
						$eid = IPS_GetObjectIDByIdent("DelayTimer", $this->InstanceID);
					}
					$delay = GetValue(IPS_GetObjectIDByIdent("DelayVar", $this->InstanceID));
					if(IPS_VariableProfileExists("~UnixTimestampTime"))
					{
						$delay += 3600;
					}
					IPS_SetEventCyclic($eid, 0 /* Keine Datumsüberprüfung */, 0, 0, 2, 1 /* Sekündlich */ , $delay);
					IPS_SetEventActive($eid, true);

					if(@IPS_GetObjectIDByIdent("NachlaufTimer", $this->InstanceID) !== false)
					{
						$eid = IPS_GetObjectIDByIdent("NachlaufTimer", $this->InstanceID);
						IPS_SetEventCyclicTimeFrom($eid, (int)date("H"), (int)date("i"), (int)date("s"));
						IPS_SetEventCyclic($eid, 0 /* Keine Datumsüberprüfung */, 0, 0, 2, 1 /* Sekündlich */ , $nachlaufzeit + $delay /*Minuten zu Sekunden*/ /* Alle 2 Minuten */);
						IPS_SetEventActive($eid, true);
						IPS_SetHidden($eid,false);
					}
				}
				else
				{
					$ntVarChanged = IPS_GetVariable($ntID)['VariableChanged'];
					$time = time();
					if($ntVarChanged < $time + 2 && $ntVarChanged > $time - 2)
					{
						if(@IPS_GetObjectIDByIdent("NachlaufTimer", $this->InstanceID) !== false)
						{
							$eid = IPS_GetObjectIDByIdent("NachlaufTimer", $this->InstanceID);
							IPS_SetEventCyclicTimeFrom($eid, (int)date("H"), (int)date("i"), (int)date("s"));
							IPS_SetEventCyclic($eid, 0 /* Keine Datumsüberprüfung */, 0, 0, 2, 1 /* Sekündlich */ , $nachlaufzeit + $delay /*Minuten zu Sekunden*/ /* Alle 2 Minuten */);
							IPS_SetEventActive($eid, true);
							IPS_SetHidden($eid,false);
						}
					}
					if(@IPS_GetObjectIDByIdent("NachlaufTimer", $this->InstanceID) !== false)
					{
						$eid = IPS_GetObjectIDByIdent("NachlaufTimer", $this->InstanceID);
						$eventActive = IPS_GetEvent($eid)['EventActive'];
						if($eventActive)
						{
							$this->nachlaufzeitAbgelaufen = false;
						}
						else
						{
							$this->nachlaufzeitAbgelaufen = true;
						}
					}
					else
					{
						$this->nachlaufzeitAbgelaufen = true;
					}

					if($this->nachlaufzeitAbgelaufen == true)
					{
						$_IPS['SELF'] = "WebFront";
						SetValue($statusID,0);
					}
				}
			}
			if(@IPS_GetObjectIDByIdent("VerzogerungOnChange",$this->InstanceID) === false)
			{
				$vid = IPS_GetObjectIDByIdent("DelayVar", $this->InstanceID);
				$eid = IPS_CreateEvent(0 /*ausgelöst*/);
				IPS_SetName($eid, "Verzögerung OnChange");
				IPS_SetParent($eid, $this->InstanceID);
				IPS_SetIdent($eid, "VerzogerungOnChange");
				IPS_SetIcon($eid, "Clock");
				IPS_SetEventTrigger($eid,1,$vid);
				IPS_SetEventScript($eid,"SWT_createDelayTimer(". $this->InstanceID .");");
				IPS_SetEventActive($eid,true);
				return $eid;
			}
		}

        public function refreshStatus()
		{
			if(@IPS_GetObjectIDByIdent("DelayTimer", $this->InstanceID) !== false)
			{
				$dtid = IPS_GetObjectIDByIdent("DelayTimer", $this->InstanceID);
				IPS_SetEventActive($dtid,false);
				IPS_DeleteEvent($dtid);
			}

			$instance = $this->ReadPropertyInteger("instance");
			$automatik = IPS_GetObjectIDByIdent("Automatik",$instance);
			$automatik = GetValue($automatik);
			if($automatik)
			{
				$sid = $this->ReadPropertyInteger("Sensor");
				$sid2 = $this->ReadPropertyInteger("Sensor2");
				$sid3 =$this->ReadPropertyInteger("Sensor3");
				$lid = IPS_GetObjectIDByIdent("limit", $this->InstanceID);
				$lid2 = @IPS_GetObjectIDByIdent("limit2", $this->InstanceID);
				$lid3 = @IPS_GetObjectIDByIdent("limit3", $this->InstanceID);
				$statusID = IPS_GetObjectIDByIdent("Status", $this->InstanceID);
				$ntID = IPS_GetObjectIDByIdent("NachlaufzeitVariable", $this->InstanceID);

				//limits
				try
				{
					$limit = GetValue($lid);
				}
				catch(Exception $e)
				{
					echo 'can not get Value of Limit ' . $lid . '\n', $e->GetMessage(), '\n';
				}
				if($sid2 >= 10000)
					$limit2 = GetValue($lid2);
				else
					$limit2 = PHP_INT_MIN;
				if($sid3 >= 10000)
					$limit3 = GetValue($lid3);
				else
					$limit3 = PHP_INT_MIN;
				//sensors
				$sensor = @GetValue($sid);
				if($sid2 >= 10000)
					$sensor2 = GetValue($sid2);
				else
					$sensor2 = PHP_INT_MAX;
				if($sid3 >= 10000)
					$sensor3 = GetValue($sid3);
				else
					$sensor3 = PHP_INT_MAX;

				$nachlaufzeit = GetValue($ntID);
				//format timestamp
				if(IPS_VariableProfileExists("~UnixTimestampTime"))
				{
					$nachlaufzeit += 3600;
				}
				if($nachlaufzeit < 1) { $nachlaufzeit = 0.05; }
				if($limit < $sensor && $limit2 < $sensor2 && $limit3 < $sensor3) //Above limit
				{
					$_IPS['SELF'] = "WebFront";
					SetValue($statusID,1);

					if(@IPS_GetObjectIDByIdent("NachlaufTimer", $this->InstanceID) === false)
					{
						$eid = IPS_CreateEvent(1 /*züklisch*/);
						IPS_SetName($eid, "Timer");
						IPS_SetParent($eid, $this->InstanceID);
						IPS_SetIdent($eid, "NachlaufTimer");
						IPS_SetPosition($eid, 4);
						IPS_SetEventScript($eid, "SWT_nachlaufzeitAbgelaufen(". $this->InstanceID .");");
					}
					else
					{
						$eid = IPS_GetObjectIDByIdent("NachlaufTimer", $this->InstanceID);
					}
					IPS_SetEventCyclicTimeFrom($eid, (int)date("H"), (int)date("i"), (int)date("s"));
					IPS_SetEventCyclic($eid, 0 /* Keine Datumsüberprüfung */, 0, 0, 2, 1 /* Sekündlich */ , $nachlaufzeit /*Minuten zu Sekunden*/ /* Alle 2 Minuten */);
					IPS_SetEventActive($eid, true);
					IPS_SetHidden($eid,false);

					$this->nachlaufzeitAbgelaufen = false;
				}
				else //Below limit
				{
					if(@IPS_GetObjectIDByIdent("NachlaufTimer", $this->InstanceID) !== false)
					{
						$eid = IPS_GetObjectIDByIdent("NachlaufTimer", $this->InstanceID);
						$eventActive = IPS_GetEvent($eid)['EventActive'];
						if($eventActive)
						{
							$this->nachlaufzeitAbgelaufen = false;
						}
						else
						{
							$this->nachlaufzeitAbgelaufen = true;
						}
					}
					else
					{
						$this->nachlaufzeitAbgelaufen = true;
					}

					if($this->nachlaufzeitAbgelaufen == true)
					{
						$_IPS['SELF'] = "WebFront";
						SetValue($statusID,0);
					}
				}
			}
			return $_IPS['SELF'];
        }

		public function nachlaufzeitAbgelaufen()
		{
			$this->nachlaufzeitAbgelaufen = true;

			$eid = IPS_GetObjectIDByIdent("NachlaufTimer", $this->InstanceID );
			IPS_SetHidden($eid,true);
			IPS_SetEventActive($eid,false);
			IPS_Sleep(100);
			$this->refreshStatus();
		}

		public function statusOnChange()
		{
			$vid = IPS_GetObjectIDByIdent("Status", $this->InstanceID);
			$status = GetValue($vid);
			$targets = IPS_GetObjectIDByIdent("Targets", IPS_GetParent($this->InstanceID));
			if($status === true /*ON*/)
			{
				$value = $this->ReadPropertyString("valueOn");
				$script = $this->ReadPropertyInteger("scriptOn");
			}
			else /*OFF*/
			{
				$value = $this->ReadPropertyString("valueOff");
				$script = $this->ReadPropertyInteger("scriptOff");
			}

			if($value != "")
			{
				foreach(IPS_GetChildrenIDs($targets) as $target)
				{
					/*only allow links*/
					if(IPS_LinkExists($target))
					{
						$linkVariableID = IPS_GetLink($target)['TargetID'];
						if(IPS_VariableExists($linkVariableID))
						{
							$type = IPS_GetVariable($linkVariableID)['VariableType'];
							$id = $linkVariableID;

							$o = IPS_GetObject($id);
							$v = IPS_GetVariable($id);

							if($v['VariableType'] == 0)
							{
								$value = (bool) $value;
							}

							if($v["VariableCustomAction"] > 0)
								$actionID = $v["VariableCustomAction"];
							else
								$actionID = $v["VariableAction"];

							/*Skip this device if we do not have a proper id*/
								if($actionID < 10000)
								{
									SetValue($id,$value);
									continue;
								}
							if(IPS_InstanceExists($actionID))
							{
								IPS_RequestAction($actionID, $o["ObjectIdent"], $value);
							}
							else if(IPS_ScriptExists($actionID))
							{
								echo IPS_RunScriptWaitEx($actionID, Array("VARIABLE" => $id, "VALUE" => $value, "SENDER" => "WebFront"));
							}
						}
					} else {

						if(IPS_GetObject($target)['ObjectType'] == 2) {

							$type = IPS_GetVariable($target)['VariableType'];

							$asObject = IPS_GetObject($target);
							$asVariable = IPS_GetVariable($target);

							if ($asVariable['VariableType'] == 0) {

								SetValue($target, $value);

							}

						}

					}
				}
			}
			else
			{
				IPS_LogMessage("SWT", "The Value was empty, Target remains unchanged");
			}
			if($script > 9999)
			{
				$e = @IPS_RunScript($script);
				if(!$e) 
					IPS_LogMessage("SWT", "The Script couldn't be run");
			}
			else
			{
				IPS_LogMessage("SWT", "The ScriptID was invalid, No script was executed");
			}
		}

		protected function Del($id, $bool = false /*Delete associated files along with the objects ?*/)
		{
			if(IPS_HasChildren($id))
			{
				$childIDs = IPS_GetChildrenIDs($id);
				foreach($childIDs as $child)
				{
					$this->Del($child);
				}
				$this->Del($id);
			}
			else
			{
				$type = IPS_GetObject($id)['ObjectType'];
				switch($type)
				{
					case(0):
						IPS_DeleteCategory($id);
						break;
					case(1):
						IPS_DeleteInstance($id);
						break;
					case(2):
						IPS_DeleteVariable($id);
						break;
					case(3):
						IPS_DeleteScript($id);
						break;
					case(4):
						IPS_DeleteEvent($id);
						break;
					case(5):
						IPS_DeleteMedia($id, $bool /*dont delete media file along with it*/);
						break;
					case(6):
						IPS_DeleteLink($id);
				}
			}
		}

		protected function CreateLink($content)
		{
			/**
			 * 
			 * 
			 * @param <array> $content 
			 * 
			 * @return <integer> $LinkID
			 
			$content = array("ObjectName" => "LinkName",
							 "ParentID" => ParentID,
							 "ObjectIdent" => "Identity",
							 "TargetID" => TargetID,
							 "ObjectInfo" => "Info", //optional
							 "ObjectIsHidden" => Boolean, //optional
							 "ObjectPosition" => position, //optional
							 "ObjectIcon" => "Icon" //optional
							)
			 */
			if(@IPS_GetObjectIDByIdent($content["ObjectIdent"], $content["parentID"]) === false)
			{
				$id = IPS_CreateLink();
				IPS_SetName($id, $content['ObjectName']);
				IPS_SetParent($id, $content['ParentID']);
				IPS_SetIdent($id, $content['ObjectIdent']);
				if(array_key_exists("ObjectInfo", $content))
					IPS_SetInfo($id, $content["ObjectInfo"]);
				if(array_key_exists("ObjectIsHidden", $content))
					IPS_SetHidden($id, $content["ObjectIsHidden"]);
				if(array_key_exists("ObjectPosition", $content))
					IPS_SetPosition($id, $content["ObjectPosition"]);
				if(array_key_exists("ObjectIcon", $content))
					IPS_SetIcon($id, $content["ObjectIcon"]);
				IPS_SetLinkTargetID($id, $content["TargetID"]);
			}
			else
			{
				$id = IPS_GetObjectIDByIdent($content["ObjectIdent"], $content["ParentID"]);
			}
			return $id;
		}

		private function CreateCategoryByIdent($id, $ident, $name)
		{
			$cid = @IPS_GetObjectIDByIdent($ident, $id);
			if($cid === false) {
				$cid = IPS_CreateCategory();
				IPS_SetParent($cid, $id);
				IPS_SetName($cid, $name);
				IPS_SetIdent($cid, $ident);
			}
			return $cid;
		}
    }
?>
