// ----------------------------------------------------------------------------
// Copyright 2006-2010, GeoTelematic Solutions, Inc.
// All rights reserved
// ----------------------------------------------------------------------------
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
// 
// http://www.apache.org/licenses/LICENSE-2.0
// 
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.
//
// ----------------------------------------------------------------------------
// Change History:
//  2008/05/14  Martin D. Flynn
//     -Initial release
// ----------------------------------------------------------------------------
package org.opengts.db;

import java.lang.*;
import java.util.*;
import java.math.*;
import java.io.*;
import java.sql.*;

import org.opengts.util.*;
import org.opengts.dbtools.*;

import org.opengts.db.*;
import org.opengts.db.tables.Account;
import org.opengts.db.tables.Device;

public class DeviceRecord<RT extends DBRecord>
    extends AccountRecord<RT>
{

    // ------------------------------------------------------------------------
    // ------------------------------------------------------------------------

    /* common Asset/Device field definition */
    public static final String FLD_deviceID = "deviceID";

    /* create a new "deviceID" key field definition */
    public static DBField newField_deviceID(boolean key)
    {
        return DeviceRecord.newField_deviceID(key, null, null);
    }

    /* create a new "deviceID" key field definition */
    public static DBField newField_deviceID(boolean key, String title)
    {
        return DeviceRecord.newField_deviceID(key, title, null);
    }

    /* create a new "deviceID" key field definition */
    public static DBField newField_deviceID(boolean key, String title, String extraArg)
    {
        if (StringTools.isBlank(title)) { title = "Device/Asset ID"; }
        String arg = (key?"key=true":"edit=2") + " " + StringTools.trim(extraArg);
        return new DBField(FLD_deviceID, String.class, DBField.TYPE_DEV_ID(), title, arg);
    }

    // ------------------------------------------------------------------------
    // ------------------------------------------------------------------------

    public static abstract class DeviceKey<RT extends DBRecord>
        extends AccountKey<RT>
    {
        public DeviceKey() {
            super();
        }
    }

    // ------------------------------------------------------------------------
    // ------------------------------------------------------------------------

    /* Bean instance */
    public DeviceRecord()
    {
        super();
    }

    /* database record */
    public DeviceRecord(DeviceKey<RT> key)
    {
        super(key);
    }
         
    // ------------------------------------------------------------------------

    /* Device ID */
    public final String getDeviceID()
    {
        String v = (String)this.getFieldValue(FLD_deviceID);
        return (v != null)? v : "";
    }
    
    public /*final*/ void setDeviceID(String v)
    {
        this.setFieldValue(FLD_deviceID, ((v != null)? v : ""));
    }

    // ------------------------------------------------------------------------
    // The following is an optimization for holding the Account record while
    // processing this DeviceRecord.  Use with caution.
    
    private Device device = null;

    public final boolean hasDevice()
    {
        return (this.device != null);
    }

    /* get the device for this event */
    public final Device getDevice()
    {
        if (this.device == null) {
            String deviceID = this.getDeviceID();
            Print.logInfo("[Optimize] Retrieving Device record: " + this.getAccountID() + "/" + deviceID);
            try {
                this.device = Device.getDevice(this.getAccount(), deviceID);
                if (this.device == null) {
                    // 'this.device' may still be null if the asset was not found
                    Print.logError("Device not found: " + this.getAccountID() + "/" + deviceID);
                }
            } catch (DBException dbe) {
                // may be caused by "java.net.ConnectException: Connection refused: connect"
                Print.logError("Device not found: " + this.getAccountID() + "/" + deviceID);
                this.device = null;
            }
        }
        return this.device;
    }

    /* set the device for this record */
    public final void setDevice(Device dev) 
    {
        if ((Object)this instanceof Device) {
            if (this != dev) {
                Print.logError("'this' is already an Device: " + this.getAccountID() + "/" + this.getDeviceID());
            }
        } else
        if (dev == null) {
            this.device = null;
        } else
        if (!this.getAccountID().equals(dev.getAccountID()) ||
            !this.getDeviceID().equals(dev.getDeviceID()  )   ) {
            Print.logError("Account/Device IDs do not match: " + this.getAccountID() + ":" + dev.getAccountID() + "/" + this.getDeviceID() + ":" + dev.getDeviceID());
            this.device = null;
        } else {
            this.setAccount(dev.getAccount());
            this.device = dev;
        }
    }

    // ------------------------------------------------------------------------
    
    private String  deviceDesc = null;
    private String  deviceVIN  = null;

    /**
    *** Return the description for this DBRecord's Device
    *** @return The Device description
    **/
    public final String getDeviceDescription()
    {
        if (this.deviceDesc == null) {
            Device dev = this.getDevice();
            this.deviceDesc = (dev != null)? dev.getDescription() : this.getDeviceID();
        } 
        return this.deviceDesc;
    }

    /**
    *** Return the short Vehicle-ID description for this DBRecord's Device
    *** @return The Device short Vehicle-ID description
    **/
    public final String getDeviceVIN()
    {
        if (this.deviceVIN == null) {
            Device dev = this.getDevice();
            if (dev != null) {
                this.deviceVIN = StringTools.blankDefault(dev.getDisplayName(),dev.getVehicleID());
            }
            if (StringTools.isBlank(this.deviceVIN)) {
                this.deviceVIN = this.getDeviceID();
            }
        } 
        return this.deviceVIN;
    }

    // ------------------------------------------------------------------------

}
