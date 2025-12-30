#!/usr/bin/env node

/**
 * Asterisk ARI Dialer - Stasis Application
 * Main entry point for the dialer system
 */

require('dotenv').config();
const ari = require('ari-client');
const mysql = require('mysql2/promise');
const winston = require('winston');

// Configuration
const config = {
  ari: {
    url: `http://${process.env.ARI_HOST}:${process.env.ARI_PORT}`,
    username: process.env.ARI_USERNAME,
    password: process.env.ARI_PASSWORD,
    app: process.env.ARI_APP_NAME
  },
  db: {
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
  },
  debug: process.env.DEBUG_MODE === 'true',
  recordingsPath: process.env.RECORDINGS_PATH,
  soundsPath: process.env.SOUNDS_PATH
};

// Setup logger
const logger = winston.createLogger({
  level: process.env.LOG_LEVEL || 'info',
  format: winston.format.combine(
    winston.format.timestamp(),
    winston.format.json()
  ),
  transports: [
    new winston.transports.Console({
      format: winston.format.combine(
        winston.format.colorize(),
        winston.format.simple()
      )
    }),
    new winston.transports.File({ filename: '../logs/stasis-error.log', level: 'error' }),
    new winston.transports.File({ filename: '../logs/stasis-combined.log' })
  ]
});

// Database connection pool
let dbPool;

// Active campaigns and calls tracking
const activeCampaigns = new Map();
const activeCalls = new Map();
const channelRecordings = new Map();

/**
 * Initialize database connection
 */
async function initDatabase() {
  try {
    dbPool = await mysql.createPool(config.db);
    logger.info('Database connection pool created');

    // Test connection
    const connection = await dbPool.getConnection();
    await connection.ping();
    connection.release();
    logger.info('Database connection successful');

    return true;
  } catch (err) {
    logger.error('Database connection failed:', err);
    return false;
  }
}

/**
 * Get active campaigns from database
 */
async function loadActiveCampaigns() {
  try {
    const [campaigns] = await dbPool.execute(
      'SELECT * FROM campaigns WHERE status = ?',
      ['running']
    );

    // Get IDs of campaigns that should be running
    const runningCampaignIds = new Set(campaigns.map(c => c.id));

    // Stop campaigns that are no longer running
    for (const [campaignId, campaign] of activeCampaigns.entries()) {
      if (!runningCampaignIds.has(campaignId)) {
        logger.info(`Campaign ${campaignId} is no longer running - stopping it`);
        stopCampaign(campaignId);
      }
    }

    // Start or update running campaigns
    for (const campaign of campaigns) {
      if (!activeCampaigns.has(campaign.id)) {
        // New campaign - add it and start processing
        logger.info(`New campaign ${campaign.id} detected - starting it`);
        activeCampaigns.set(campaign.id, {
          ...campaign,
          currentCalls: 0,
          processInterval: null
        });
        startCampaign(campaign.id);
      } else {
        // Existing campaign - update its data (settings may have changed)
        const existing = activeCampaigns.get(campaign.id);
        // Preserve runtime state but update campaign settings
        activeCampaigns.set(campaign.id, {
          ...campaign,
          currentCalls: existing.currentCalls,
          processInterval: existing.processInterval
        });
        logger.debug(`Updated campaign ${campaign.id} data`);
      }
    }

    logger.info(`Loaded ${campaigns.length} active campaigns`);
  } catch (err) {
    logger.error('Failed to load active campaigns:', err);
  }
}

/**
 * Start processing a campaign
 */
function startCampaign(campaignId) {
  const campaign = activeCampaigns.get(campaignId);
  if (!campaign) return;

  logger.info(`Starting campaign ${campaignId}: ${campaign.name}`);

  // Process campaign interval in milliseconds
  // 100ms = 10 times/sec (fast), 1000ms = 1/sec, 3000ms = every 3 seconds
  const processIntervalMs = process.env.CAMPAIGN_PROCESS_INTERVAL || 100;

  campaign.processInterval = setInterval(() => {
    processCampaign(campaignId);
  }, processIntervalMs);

  logger.debug(`Campaign ${campaignId} process interval set to ${processIntervalMs}ms`);
}

/**
 * Stop processing a campaign
 */
async function stopCampaign(campaignId) {
  const campaign = activeCampaigns.get(campaignId);
  if (!campaign) return;

  logger.info(`Stopping campaign ${campaignId}`);

  if (campaign.processInterval) {
    clearInterval(campaign.processInterval);
    campaign.processInterval = null;
  }

  // Check if campaign is fully stopped (not just paused)
  // Only hang up active calls if campaign status is 'stopped', not 'paused'
  try {
    const [rows] = await dbPool.execute(
      'SELECT status FROM campaigns WHERE id = ?',
      [campaignId]
    );

    if (rows.length > 0 && rows[0].status === 'stopped') {
      // Campaign is fully stopped - hang up all active calls
      logger.info(`Campaign ${campaignId} is stopped - hanging up all active calls`);
      for (const [channelId, callInfo] of activeCalls.entries()) {
        if (callInfo.campaignId === campaignId) {
          logger.info(`Hanging up active call ${channelId} due to campaign stop`);
          try {
            // Try to hangup the channel via ARI
            ariClient.channels.hangup({ channelId: channelId }).catch(err => {
              logger.warn(`Failed to hangup channel ${channelId}: ${err.message}`);
            });
          } catch (err) {
            logger.warn(`Error hanging up channel ${channelId}: ${err.message}`);
          }
        }
      }
    } else {
      logger.info(`Campaign ${campaignId} is paused - keeping active calls running`);
    }
  } catch (err) {
    logger.error(`Error checking campaign ${campaignId} status:`, err);
  }

  activeCampaigns.delete(campaignId);
}

/**
 * Process campaign - dial next numbers
 */
async function processCampaign(campaignId) {
  const campaign = activeCampaigns.get(campaignId);
  if (!campaign || campaign.status === 'paused') return;

  try {
    // Check if we can make more calls
    const availableSlots = campaign.concurrent_calls - campaign.currentCalls;

    if (availableSlots <= 0) {
      logger.debug(`Campaign ${campaignId}: No available slots (concurrent=${campaign.concurrent_calls}, current=${campaign.currentCalls})`);
      return;
    }

    // Get pending numbers to dial
    const [numbers] = await dbPool.execute(
      `SELECT * FROM campaign_numbers
       WHERE campaign_id = ? AND status = 'pending'
       ORDER BY id ASC
       LIMIT ?`,
      [campaignId, availableSlots]
    );

    if (numbers.length > 0) {
      logger.debug(`Campaign ${campaignId}: Found ${numbers.length} pending numbers, ${availableSlots} slots available`);
    }

    // Dial each number
    for (const number of numbers) {
      dialNumber(campaign, number);
    }
  } catch (err) {
    logger.error(`Error processing campaign ${campaignId}:`, err);
  }
}

/**
 * Dial a number for a campaign
 */
async function dialNumber(campaign, numberRecord) {
  try {
    // Update status to calling
    await dbPool.execute(
      'UPDATE campaign_numbers SET status = ?, last_attempt = NOW(), attempts = attempts + 1 WHERE id = ?',
      ['calling', numberRecord.id]
    );

    // Build trunk endpoint
    let endpoint;
    if (campaign.trunk_type === 'custom') {
      endpoint = campaign.trunk_value.replace('${EXTEN}', numberRecord.phone_number);
    } else if (campaign.trunk_type === 'pjsip') {
      // PJSIP format: PJSIP/number@endpoint
      endpoint = `PJSIP/${numberRecord.phone_number}@${campaign.trunk_value}`;
    } else if (campaign.trunk_type === 'sip') {
      // SIP (chan_sip) format: SIP/trunk/number
      endpoint = `SIP/${campaign.trunk_value}/${numberRecord.phone_number}`;
    } else {
      // Fallback: uppercase trunk type
      endpoint = `${campaign.trunk_type.toUpperCase()}/${numberRecord.phone_number}@${campaign.trunk_value}`;
    }

    // Originate call
    const channelId = `dialer-${campaign.id}-${numberRecord.id}-${Date.now()}`;
    const callerIdNumber = campaign.callerid || 'Unknown';
    const dialTimeout = campaign.dial_timeout || 30; // Use campaign dial timeout (default 30 sec)

    const originateParams = {
      endpoint: endpoint,
      app: config.ari.app,
      appArgs: `campaign=${campaign.id},number=${numberRecord.id}`,
      callerId: callerIdNumber,
      channelId: channelId,
      timeout: dialTimeout
    };

    logger.info(`ARI POST /channels - Originating call to ${numberRecord.phone_number}`);
    logger.info(`ARI REQUEST: ${JSON.stringify(originateParams, null, 2)}`);

    const response = await ariClient.channels.originate(originateParams);

    logger.info(`ARI RESPONSE: Channel created successfully - ID: ${channelId}`);

    // Track the call
    activeCalls.set(channelId, {
      campaignId: campaign.id,
      numberId: numberRecord.id,
      phoneNumber: numberRecord.phone_number,
      startTime: new Date(),
      answerTime: null,
      status: 'calling',
      agentChannel: null,
      bridge: null,
      recordings: []
    });

    // Increment current calls for campaign
    campaign.currentCalls++;

    // Create CDR record
    await dbPool.execute(
      `INSERT INTO cdr (campaign_id, campaign_number_id, channel_id, callerid, destination, start_time, disposition)
       VALUES (?, ?, ?, ?, ?, NOW(), 'calling')`,
      [campaign.id, numberRecord.id, channelId, callerIdNumber, numberRecord.phone_number]
    );

  } catch (err) {
    logger.error(`ARI ERROR - Failed to dial ${numberRecord.phone_number}`);
    logger.error(`Error message: ${err.message}`);
    if (err.stack) {
      logger.error(`Error stack: ${err.stack}`);
    }

    // CRITICAL: If we added the call to activeCalls, clean it up
    // This prevents slot counter leaks when calls fail after being tracked
    if (activeCalls.has(channelId)) {
      campaign.currentCalls--;
      activeCalls.delete(channelId);
      logger.info(`Cleaned up failed call ${channelId}: decremented currentCalls to ${campaign.currentCalls}`);
    }

    // Mark as failed
    await dbPool.execute(
      'UPDATE campaign_numbers SET status = ? WHERE id = ?',
      ['failed', numberRecord.id]
    );
  }
}

/**
 * Handle agent channel entering Stasis
 */
async function handleAgentChannel(agentChannel, customerChannelId) {
  try {
    const callInfo = activeCalls.get(customerChannelId);
    if (!callInfo) {
      logger.error(`Customer channel ${customerChannelId} not found for agent ${agentChannel.id}`);
      agentChannel.hangup();
      return;
    }

    if (!callInfo.bridge) {
      logger.error(`No bridge found for customer ${customerChannelId}`);
      agentChannel.hangup();
      return;
    }

    // Update the agent channel ID to the actual channel that entered Stasis
    // This is important because the originated channel ID may differ from the one that enters Stasis
    callInfo.agentChannel = agentChannel.id;
    logger.info(`Updated agent channel ID to ${agentChannel.id} for customer ${customerChannelId}`);

    logger.info(`ARI POST /bridges/${callInfo.bridge}/addChannel - Adding agent ${agentChannel.id}`);

    // Add agent to bridge using bridges.addChannel
    await ariClient.bridges.addChannel({
      bridgeId: callInfo.bridge,
      channel: agentChannel.id
    });

    logger.info(`ARI RESPONSE: Agent channel ${agentChannel.id} added to bridge ${callInfo.bridge}`);

    // Update CDR with agent info
    await dbPool.execute(
      'UPDATE cdr SET agent = ? WHERE channel_id = ?',
      [agentChannel.name || agentChannel.id, customerChannelId]
    );

    logger.info(`Agent ${agentChannel.id} successfully connected to customer ${customerChannelId}`);

  } catch (err) {
    logger.error(`Failed to handle agent channel ${agentChannel.id}:`, err);
    try {
      agentChannel.hangup();
    } catch (hangupErr) {
      logger.error(`Failed to hangup agent channel:`, hangupErr);
    }
  }
}

/**
 * Handle Stasis start event
 */
function stasisStart(event, channel) {
  logger.info(`Channel ${channel.id} entered Stasis application`);

  // Ignore snoop channels - they're used for recording only
  if (channel.id.startsWith('snoop-')) {
    logger.info(`Snoop channel ${channel.id} entered Stasis - ignoring (used for recording)`);
    return;
  }

  // Check if this is an agent channel by parsing appArgs
  const args = event.args;
  let isAgentChannel = false;
  let customerChannelId = null;

  if (args && args.length > 0) {
    for (const arg of args) {
      if (arg.startsWith('type=agent')) {
        isAgentChannel = true;
      }
      if (arg.startsWith('call=')) {
        customerChannelId = arg.split('=')[1];
      }
    }
  }

  // If it's an agent channel, find the customer call info and add it to the bridge
  if (isAgentChannel && customerChannelId) {
    logger.info(`Agent channel ${channel.id} entered Stasis for customer ${customerChannelId}`);
    handleAgentChannel(channel, customerChannelId);
    return;
  }

  const callInfo = activeCalls.get(channel.id);
  if (!callInfo) {
    logger.warn(`Unknown channel ${channel.id} entered Stasis (not agent, not in activeCalls)`);
    return;
  }

  // Answer the channel
  logger.info(`ARI POST /channels/${channel.id}/answer - Answering channel`);

  channel.answer((err) => {
    if (err) {
      logger.error(`ARI ERROR - Failed to answer channel ${channel.id}`);
      logger.error(`Error: ${JSON.stringify(err, Object.getOwnPropertyNames(err), 2)}`);
      hangupCall(channel.id, 'failed');
      return;
    }

    logger.info(`ARI RESPONSE: Channel ${channel.id} answered successfully`);
    callInfo.answerTime = new Date();
    callInfo.status = 'answered';

    // Update CDR
    dbPool.execute(
      'UPDATE cdr SET answer_time = NOW(), disposition = ? WHERE channel_id = ?',
      ['answered', channel.id]
    );

    // Update campaign number status
    dbPool.execute(
      'UPDATE campaign_numbers SET status = ? WHERE id = ?',
      ['answered', callInfo.numberId]
    );

    // Get campaign to check call_timeout
    const campaign = activeCampaigns.get(callInfo.campaignId);
    if (campaign && campaign.call_timeout) {
      const callTimeout = (campaign.call_timeout || 300) * 1000; // Convert to milliseconds
      logger.info(`Setting call timeout to ${campaign.call_timeout} seconds for channel ${channel.id}`);

      // Set call duration timeout
      callInfo.callTimeoutTimer = setTimeout(() => {
        logger.info(`Call timeout reached (${campaign.call_timeout}s) for channel ${channel.id} - hanging up`);
        hangupCall(channel.id, 'completed');
      }, callTimeout);
    }

    // Connect to agent
    connectToAgent(channel, callInfo);
  });
}

/**
 * Connect answered customer to agent
 */
async function connectToAgent(customerChannel, callInfo) {
  try {
    const campaign = activeCampaigns.get(callInfo.campaignId);
    if (!campaign) {
      logger.error(`Campaign ${callInfo.campaignId} not found`);
      hangupCall(customerChannel.id, 'failed');
      return;
    }

    let agentEndpoint;

    // Determine agent destination
    if (campaign.agent_dest_type === 'custom') {
      agentEndpoint = campaign.agent_dest_value;
    } else if (campaign.agent_dest_type === 'exten') {
      agentEndpoint = campaign.agent_dest_value;
    } else if (campaign.agent_dest_type === 'ivr') {
      // Handle IVR
      handleIVR(customerChannel, callInfo, campaign);
      return;
    }

    // Create bridge
    const bridge = ariClient.Bridge();
    logger.info(`ARI POST /bridges - Creating bridge for call ${customerChannel.id}`);
    logger.info(`ARI REQUEST: ${JSON.stringify({ type: 'mixing' }, null, 2)}`);

    await bridge.create({ type: 'mixing' });
    logger.info(`ARI RESPONSE: Bridge ${bridge.id} created successfully`);

    // Add customer channel to bridge
    logger.info(`ARI POST /bridges/${bridge.id}/addChannel - Adding customer ${customerChannel.id}`);
    await bridge.addChannel({ channel: customerChannel.id });
    logger.info(`ARI RESPONSE: Customer channel added to bridge`);

    // If recording is enabled, start recording the bridge
    if (campaign.record_calls) {
      await startBridgeRecording(bridge.id, callInfo);
    }

    // Originate call to agent with customer's number as caller ID
    const agentChannelId = `agent-${callInfo.campaignId}-${Date.now()}`;

    // Format caller ID: "Customer Name" <number> or just <number>
    const callerIdName = `Customer ${callInfo.phoneNumber}`;
    const callerIdNumber = callInfo.phoneNumber;
    const callerIdFull = `"${callerIdName}" <${callerIdNumber}>`;

    const agentOriginateParams = {
      endpoint: agentEndpoint,
      app: config.ari.app,
      appArgs: `type=agent,call=${customerChannel.id}`,
      channelId: agentChannelId,
      callerId: callerIdFull,
      timeout: 30
    };

    logger.info(`ARI POST /channels - Originating agent channel to ${agentEndpoint}`);
    logger.info(`Setting Caller ID for agent: ${callerIdFull}`);
    logger.info(`ARI REQUEST: ${JSON.stringify(agentOriginateParams, null, 2)}`);

    const agentChannel = ariClient.Channel();
    await agentChannel.originate(agentOriginateParams);

    logger.info(`ARI RESPONSE: Agent channel ${agentChannelId} originated successfully`);

    callInfo.agentChannel = agentChannelId;
    callInfo.bridge = bridge.id;

    // Agent will enter Stasis when answered, and handleAgentChannel() will add it to the bridge

  } catch (err) {
    logger.error(`Failed to connect to agent:`, err);
    hangupCall(customerChannel.id, 'failed');
  }
}

/**
 * Handle IVR for a call
 */
async function handleIVR(channel, callInfo, campaign) {
  try {
    // Get IVR menu by ID from agent_dest_value
    const ivrMenuId = campaign.agent_dest_value;

    const [menus] = await dbPool.execute(
      'SELECT * FROM ivr_menus WHERE id = ? LIMIT 1',
      [ivrMenuId]
    );

    if (menus.length === 0) {
      logger.error(`No IVR menu found with ID ${ivrMenuId} for campaign ${campaign.id}`);
      hangupCall(channel.id, 'failed');
      return;
    }

    const menu = menus[0];
    logger.info(`Loading IVR menu "${menu.name}" (ID: ${menu.id}) for call ${channel.id}`);

    // Get IVR actions
    const [actions] = await dbPool.execute(
      'SELECT * FROM ivr_actions WHERE ivr_menu_id = ?',
      [menu.id]
    );

    // Play audio file and wait for DTMF
    // Remove file extension and add dialer/ prefix
    const audioFile = menu.audio_file.replace(/\.(wav|gsm|ulaw|alaw)$/i, '');
    const audioPath = `sound:dialer/${audioFile}`;

    logger.info(`ARI POST /channels/${channel.id}/play - Playing IVR audio`);
    logger.info(`ARI REQUEST: ${JSON.stringify({ media: audioPath }, null, 2)}`);

    let dtmfReceived = false;
    let dtmfTimeout = null;
    const timeout = menu.timeout || 3; // Default 3 seconds

    const playback = ariClient.Playback();
    channel.play({ media: audioPath }, playback, (err) => {
      if (err) {
        logger.error(`ARI ERROR - Failed to play IVR audio ${audioPath}`);
        logger.error(`Error: ${JSON.stringify(err, Object.getOwnPropertyNames(err), 2)}`);
        return;
      }
      logger.info(`ARI RESPONSE: Successfully started playback ${playback.id} of ${audioPath}`);
    });

    // Listen for playback finished event
    playback.on('PlaybackFinished', (event, playback) => {
      if (!dtmfReceived) {
        logger.info(`IVR audio playback finished for ${playback.id} - starting ${timeout}s timeout for DTMF input`);

        // Set timeout for DTMF input AFTER playback finishes
        dtmfTimeout = setTimeout(() => {
          if (!dtmfReceived) {
            logger.info(`DTMF timeout after ${timeout} seconds on channel ${channel.id}`);
            // Find timeout action
            const timeoutAction = actions.find(a => a.dtmf_digit === 't');
            if (timeoutAction) {
              executeIVRAction(channel, callInfo, timeoutAction, actions, playback);
            } else {
              logger.warn(`No timeout action configured for IVR menu ${menu.id}`);
              hangupCall(channel.id, 'completed');
            }
          }
        }, timeout * 1000);
      }
    });

    // Listen for DTMF events
    channel.on('ChannelDtmfReceived', async (event, channel) => {
      const digit = event.digit;
      logger.info(`DTMF ${digit} received on channel ${channel.id}`);

      dtmfReceived = true;
      clearTimeout(dtmfTimeout);

      // Find matching action
      const action = actions.find(a => a.dtmf_digit === digit);
      if (!action) {
        logger.warn(`No action for DTMF ${digit} - checking for invalid handler`);
        // Find invalid action
        const invalidAction = actions.find(a => a.dtmf_digit === 'i');
        if (invalidAction) {
          executeIVRAction(channel, callInfo, invalidAction, actions, playback);
        } else {
          logger.warn(`No invalid action configured for IVR menu ${menu.id}`);
        }
        return;
      }

      executeIVRAction(channel, callInfo, action, actions, playback);
    });

  } catch (err) {
    logger.error(`IVR handling failed:`, err);
    hangupCall(channel.id, 'failed');
  }
}

/**
 * Execute an IVR action
 */
async function executeIVRAction(channel, callInfo, action, actions, playback = null) {
  try {
    logger.info(`Executing IVR action: ${action.action_type} - ${action.action_value}`);

    // Stop any active playback before executing action
    if (playback) {
      try {
        await playback.stop();
        logger.info(`Stopped playback ${playback.id} before executing action`);
      } catch (err) {
        // Playback might have already finished, ignore error
        logger.debug(`Could not stop playback: ${err.message}`);
      }
    }

    // Execute action
    switch (action.action_type) {
      case 'exten':
        // Connect to extension with customer's caller ID
        logger.info(`ARI POST /bridges - Creating bridge for IVR extension transfer`);
        const bridge = ariClient.Bridge();
        await bridge.create({ type: 'mixing' });
        await bridge.addChannel({ channel: channel.id });

        // Format caller ID with customer's phone number
        const callerIdName = `Customer ${callInfo.phoneNumber}`;
        const callerIdFull = `"${callerIdName}" <${callInfo.phoneNumber}>`;

        logger.info(`ARI POST /channels - Originating to extension ${action.action_value}`);
        logger.info(`Setting Caller ID: ${callerIdFull}`);

        const extChannelId = `ivr-exten-${Date.now()}`;
        const extChannel = ariClient.Channel();
        await extChannel.originate({
          endpoint: action.action_value,
          app: config.ari.app,
          channelId: extChannelId,
          callerId: callerIdFull
        });

        logger.info(`ARI RESPONSE: Extension channel originated successfully`);
        await bridge.addChannel({ channel: extChannel.id });
        logger.info(`Extension channel added to bridge`);

        // CRITICAL: Track bridge and agent channel so cleanup works when agent hangs up
        callInfo.bridge = bridge.id;
        callInfo.agentChannel = extChannelId;
        logger.info(`Tracked IVR extension bridge ${bridge.id} and agent ${extChannelId} for cleanup`);
        break;

      case 'hangup':
        logger.info(`Hanging up channel ${channel.id} per IVR action`);
        await channel.hangup().catch(err => {
          logger.warn(`Failed to hangup channel via ARI: ${err.message}`);
        });
        break;

      case 'queue':
        // Connect to queue using LOCAL/${queue}@from-internal
        const queueNumber = action.action_value;
        const queueEndpoint = `LOCAL/${queueNumber}@from-internal`;

        logger.info(`ARI POST /bridges - Creating bridge for IVR queue transfer`);
        const queueBridge = ariClient.Bridge();
        await queueBridge.create({ type: 'mixing' });
        await queueBridge.addChannel({ channel: channel.id });

        // Format caller ID with customer's phone number
        const queueCallerIdName = `Customer ${callInfo.phoneNumber}`;
        const queueCallerIdFull = `"${queueCallerIdName}" <${callInfo.phoneNumber}>`;

        logger.info(`ARI POST /channels - Originating to queue ${queueNumber} via ${queueEndpoint}`);
        logger.info(`Setting Caller ID: ${queueCallerIdFull}`);

        const queueChannelId = `ivr-queue-${Date.now()}`;
        const queueChannel = ariClient.Channel();
        await queueChannel.originate({
          endpoint: queueEndpoint,
          app: config.ari.app,
          channelId: queueChannelId,
          callerId: queueCallerIdFull
        });

        logger.info(`ARI RESPONSE: Queue channel originated successfully`);
        await queueBridge.addChannel({ channel: queueChannel.id });
        logger.info(`Queue channel added to bridge`);

        // CRITICAL: Track bridge and agent channel so cleanup works when agent hangs up
        callInfo.bridge = queueBridge.id;
        callInfo.agentChannel = queueChannelId;
        logger.info(`Tracked IVR queue bridge ${queueBridge.id} and agent ${queueChannelId} for cleanup`);
        break;

      case 'goto_ivr':
        // Go to another IVR menu by ID
        const targetIvrId = action.action_value;
        logger.info(`Going to IVR menu ID: ${targetIvrId}`);

        // Create a campaign-like object with the target IVR ID
        const virtualCampaign = {
          id: 'goto_ivr',
          agent_dest_value: targetIvrId
        };

        // Recursively call handleIVR with the new menu
        await handleIVR(channel, callInfo, virtualCampaign);
        break;

      default:
        logger.warn(`Unknown action type: ${action.action_type}`);
    }
  } catch (err) {
    logger.error(`Failed to execute IVR action:`, err);
  }
}


/**
 * Start recording a bridge (captures both sides of conversation)
 */
async function startBridgeRecording(bridgeId, callInfo) {
  try {
    // Get campaign details
    const campaign = activeCampaigns.get(callInfo.campaignId);
    if (!campaign) {
      logger.error(`Campaign ${callInfo.campaignId} not found for recording`);
      return;
    }

    // Create date-based directory structure: YYYY/MM/DD
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    const dateDir = `${year}/${month}/${day}`;
    // ARI recordings are saved to /var/spool/asterisk/recording/ by default
    const ariRecordingBase = '/var/spool/asterisk/recording';
    const fullRecordingPath = `${ariRecordingBase}/${dateDir}`;

    // Create directory structure if it doesn't exist
    const fs = require('fs');
    const path = require('path');
    const { execSync } = require('child_process');
    if (!fs.existsSync(fullRecordingPath)) {
      fs.mkdirSync(fullRecordingPath, { recursive: true, mode: 0o755 });
      // Change ownership to asterisk user so Asterisk can write recordings
      try {
        execSync(`chown -R asterisk:asterisk ${fullRecordingPath}`);
        logger.info(`Created recording directory: ${fullRecordingPath} with asterisk ownership`);
      } catch (err) {
        logger.error(`Failed to change ownership of ${fullRecordingPath}: ${err.message}`);
      }
    }

    // Generate unique ID (timestamp-based)
    const uniqueId = Date.now();

    // Clean campaign name for filename (remove special characters)
    const cleanCampaignName = campaign.name.replace(/[^a-zA-Z0-9_-]/g, '_');

    // Format: campaignname-uniqueid-number (without extension, Asterisk adds it)
    const fileBaseName = `${cleanCampaignName}-${uniqueId}-${callInfo.phoneNumber}`;
    const recordingName = `${dateDir}/${fileBaseName}`;
    const fileName = `${fileBaseName}.wav`; // Full filename with extension for logging/CDR

    logger.info(`ARI POST /bridges/${bridgeId}/record - Starting bridge recording`);
    logger.info(`Recording name: ${recordingName} (Asterisk will add .wav extension)`);
    logger.info(`Campaign: ${campaign.name}, Phone: ${callInfo.phoneNumber}, Unique ID: ${uniqueId}`);

    const recordParams = {
      name: recordingName,
      format: 'wav',
      maxDurationSeconds: 3600,
      maxSilenceSeconds: 0,
      ifExists: 'overwrite',
      beep: false,
      terminateOn: 'none'
    };

    logger.info(`ARI REQUEST: ${JSON.stringify(recordParams, null, 2)}`);

    // Start recording on the bridge - this captures both customer and agent audio
    await ariClient.bridges.record({
      bridgeId: bridgeId,
      name: recordingName,
      format: 'wav',
      maxDurationSeconds: 3600,
      maxSilenceSeconds: 0,
      ifExists: 'overwrite',
      beep: false,
      terminateOn: 'none'
    });

    logger.info(`ARI RESPONSE: Bridge recording started - ${fileName}`);
    logger.info(`Recording will be saved to: ${fullRecordingPath}/${fileName}`);

    // Track recording
    if (!channelRecordings.has(bridgeId)) {
      channelRecordings.set(bridgeId, []);
    }

    channelRecordings.get(bridgeId).push({
      recordingName: recordingName,
      fileName: fileName,
      type: 'bridge'
    });

    callInfo.recordings.push({
      file: fileName,
      fullPath: `${dateDir}/${fileName}`,
      type: 'bridge'
    });

    callInfo.bridge = bridgeId;

    // Update CDR with recording filename (relative path from recordings root)
    // Find the correct channel ID for CDR update
    const callKeys = Array.from(activeCalls.keys());
    let cdrChannelId = null;
    for (const key of callKeys) {
      const call = activeCalls.get(key);
      if (call && call.numberId === callInfo.numberId && call.campaignId === callInfo.campaignId) {
        cdrChannelId = key;
        break;
      }
    }

    if (cdrChannelId) {
      await dbPool.execute(
        `UPDATE cdr SET recording_file = ? WHERE channel_id = ?`,
        [`${dateDir}/${fileName}`, cdrChannelId]
      );

      logger.info(`CDR updated: recording_file = ${dateDir}/${fileName} for channel ${cdrChannelId}`);
    }

  } catch (err) {
    logger.error(`ARI ERROR - Failed to start bridge recording for ${bridgeId}`);
    logger.error(`Error message: ${err.message}`);
    if (err.stack) {
      logger.error(`Error stack: ${err.stack}`);
    }
  }
}

/**
 * Start recording a channel using snoop + record (DEPRECATED - use bridge recording instead)
 */
async function startRecording(channelId, callInfo, leg) {
  try {
    const recordingName = `${callInfo.campaignId}-${callInfo.numberId}-${Date.now()}-${leg}`;
    const snoopId = `snoop-${channelId}-${Date.now()}`;

    logger.info(`ARI POST /channels/${channelId}/snoop - Starting ${leg} recording`);
    logger.info(`Recording name: ${recordingName}, Snoop ID: ${snoopId}`);

    // Create snoop channel to tap into the audio
    const snoopParams = {
      channelId: channelId,
      snoopId: snoopId,
      spy: 'both',  // Record both directions (in and out)
      whisper: 'none',
      app: config.ari.app,
      appArgs: `recording=${recordingName}`
    };

    logger.info(`ARI REQUEST: ${JSON.stringify(snoopParams, null, 2)}`);

    const snoopChannel = await ariClient.channels.snoopChannel(snoopParams);

    logger.info(`ARI RESPONSE: Snoop channel ${snoopId} created successfully`);

    // Start recording on the snoop channel using the channel object
    const recordParams = {
      name: recordingName,
      format: 'wav',
      maxDurationSeconds: 3600,
      maxSilenceSeconds: 0,
      ifExists: 'overwrite',
      beep: false,
      terminateOn: 'none'
    };

    logger.info(`ARI POST /channels/${snoopId}/record - Starting recording`);
    logger.info(`ARI REQUEST: ${JSON.stringify(recordParams, null, 2)}`);

    // Use the snoop channel object's record method
    const liveRecording = ariClient.LiveRecording();
    await snoopChannel.record(recordParams, liveRecording);

    logger.info(`ARI RESPONSE: Recording started - ${recordingName}.wav`);
    logger.info(`Recording will be saved to: /var/spool/asterisk/recording/${recordingName}.wav`);

    // Track recording
    if (!channelRecordings.has(channelId)) {
      channelRecordings.set(channelId, []);
    }

    channelRecordings.get(channelId).push({
      leg: leg,
      snoopId: snoopId,
      recordingName: recordingName
    });

    callInfo.recordings.push({
      leg: leg,
      file: `${recordingName}.wav`
    });

    // Update CDR with recording filename
    const field = leg === 'customer' ? 'recording_leg1' : 'recording_leg2';

    // Find the correct channel ID for CDR update
    let cdrChannelId = channelId;
    if (leg === 'customer') {
      // For customer leg, use the original dialer channel ID
      const callKeys = Array.from(activeCalls.keys());
      for (const key of callKeys) {
        const call = activeCalls.get(key);
        if (call && call.numberId === callInfo.numberId && call.campaignId === callInfo.campaignId) {
          cdrChannelId = key;
          break;
        }
      }
    }

    await dbPool.execute(
      `UPDATE cdr SET ${field} = ? WHERE channel_id = ?`,
      [`${recordingName}.wav`, cdrChannelId]
    );

    logger.info(`CDR updated: ${field} = ${recordingName}.wav for channel ${cdrChannelId}`);

  } catch (err) {
    logger.error(`ARI ERROR - Failed to start recording for ${channelId} (${leg})`);
    logger.error(`Error message: ${err.message}`);
    if (err.stack) {
      logger.error(`Error stack: ${err.stack}`);
    }
  }
}

/**
 * Handle channel leaving bridge
 */
async function channelLeftBridge(event, instances) {
  const channel = instances.channel;
  const bridge = instances.bridge;

  logger.info(`Channel ${channel.id} left bridge ${bridge.id}`);

  // Ignore snoop/recorder channels
  if (channel.id.startsWith('snoop-') || channel.id.startsWith('Recorder/')) {
    logger.info(`Ignoring recorder channel ${channel.id} leaving bridge`);
    return;
  }

  // Find the call info - check both customer and agent channels
  let callInfo = activeCalls.get(channel.id);
  let isCustomerChannel = !!callInfo;
  let customerChannelId = channel.id;

  // If not found, this might be an agent channel - search for it
  if (!callInfo) {
    for (const [custId, info] of activeCalls.entries()) {
      if (info.agentChannel && info.agentChannel === channel.id) {
        callInfo = info;
        isCustomerChannel = false;
        customerChannelId = custId;
        break;
      }
    }
  }

  if (!callInfo) {
    logger.warn(`No call info found for channel ${channel.id} leaving bridge ${bridge.id}`);
    return;
  }

  // Check if cleanup already in progress
  if (callInfo.cleanupInProgress) {
    logger.info(`Cleanup already in progress for this call, skipping duplicate cleanup`);
    return;
  }

  // Mark cleanup as in progress to prevent duplicate cleanup
  callInfo.cleanupInProgress = true;

  logger.info(`${isCustomerChannel ? 'Customer' : 'Agent'} channel ${channel.id} left bridge - cleaning up call`);

  // CRITICAL: When agent hangs up, we must destroy both the customer channel AND the bridge
  // Hangup the other channel first
  if (isCustomerChannel && callInfo.agentChannel) {
    // Customer left, hangup agent
    logger.info(`Customer hung up - hanging up agent channel ${callInfo.agentChannel}`);
    try {
      await ariClient.channels.hangup({ channelId: callInfo.agentChannel });
      logger.info(`Agent channel ${callInfo.agentChannel} hung up successfully`);
    } catch (err) {
      logger.warn(`Failed to hangup agent ${callInfo.agentChannel}: ${err.message}`);
    }
  } else if (!isCustomerChannel && customerChannelId) {
    // Agent left, hangup customer - THIS IS THE CRITICAL PATH
    logger.info(`Agent hung up - hanging up customer channel ${customerChannelId}`);
    try {
      await ariClient.channels.hangup({ channelId: customerChannelId });
      logger.info(`Customer channel ${customerChannelId} hung up successfully`);
    } catch (err) {
      logger.warn(`Failed to hangup customer ${customerChannelId}: ${err.message}`);
    }
  }

  // Destroy the bridge - must happen after hangup to clean up resources
  try {
    logger.info(`Destroying bridge ${bridge.id} after channel cleanup`);
    await ariClient.bridges.destroy({ bridgeId: bridge.id });
    logger.info(`Bridge ${bridge.id} destroyed successfully`);
  } catch (err) {
    // Bridge might already be destroyed if both channels left - this is OK
    logger.warn(`Failed to destroy bridge ${bridge.id}: ${err.message}`);
  }
}

/**
 * Handle channel hangup
 */
function stasisEnd(event, channel) {
  logger.info(`Channel ${channel.id} left Stasis application`);

  // Ignore snoop channels - they're handled separately
  if (channel.id.startsWith('snoop-')) {
    logger.info(`Snoop channel ${channel.id} left Stasis - normal for recording end`);
    return;
  }

  // Check if this is a customer channel
  const callInfo = activeCalls.get(channel.id);

  // If cleanup is in progress (from channelLeftBridge), don't call hangupCall again
  if (callInfo && callInfo.cleanupInProgress) {
    logger.info(`Channel ${channel.id} cleanup already in progress from bridge event, skipping duplicate hangupCall`);
    return;
  }

  // Check if this is an agent channel
  if (!callInfo) {
    for (const [custId, info] of activeCalls.entries()) {
      if (info.agentChannel && info.agentChannel === channel.id) {
        if (info.cleanupInProgress) {
          logger.info(`Agent channel ${channel.id} cleanup already in progress from bridge event, skipping duplicate hangupCall`);
          return;
        }
        break;
      }
    }
  }

  hangupCall(channel.id, 'completed');
}

/**
 * Handle channel destroyed - catches early failures (BUSY, NO ANSWER, etc.)
 */
function channelDestroyed(event, channel) {
  logger.info(`Channel ${channel.id} destroyed - cause: ${event.cause} (${event.cause_txt})`);

  // Ignore snoop channels
  if (channel.id.startsWith('snoop-')) {
    return;
  }

  // Check if this is one of our active calls
  const callInfo = activeCalls.get(channel.id);

  // Also check if it's an agent channel
  let isAgentChannel = false;
  if (!callInfo) {
    for (const [custId, info] of activeCalls.entries()) {
      if (info.agentChannel && info.agentChannel === channel.id) {
        isAgentChannel = true;
        logger.info(`Destroyed channel ${channel.id} is an agent channel for customer ${custId}`);
        // Agent channels are cleaned up by channelLeftBridge or stasisEnd
        return;
      }
    }
  }

  if (!callInfo) {
    // Not one of our calls
    return;
  }

  // If cleanup already in progress, skip
  if (callInfo.cleanupInProgress) {
    logger.info(`Channel ${channel.id} cleanup already in progress, skipping`);
    return;
  }

  // Map Asterisk hangup cause to disposition
  // See: https://wiki.asterisk.org/wiki/display/AST/Hangup+Cause+Mappings
  let disposition = 'failed';
  const cause = event.cause;

  if (cause === 17 || cause === 21) {
    // 17 = USER_BUSY, 21 = CALL_REJECTED
    disposition = 'busy';
    logger.info(`Call marked as BUSY (cause ${cause})`);
  } else if (cause === 19) {
    // 19 = NO_ANSWER
    disposition = 'no-answer';
    logger.info(`Call marked as NO ANSWER (cause ${cause})`);
  } else if (cause === 16 || cause === 31) {
    // 16 = NORMAL_CLEARING, 31 = NORMAL_UNSPECIFIED
    // These usually mean answered and completed normally
    disposition = callInfo.answerTime ? 'completed' : 'no-answer';
    logger.info(`Call marked as ${disposition} (cause ${cause})`);
  } else if (cause === 34) {
    // 34 = CONGESTION
    disposition = 'congestion';
    logger.info(`Call marked as CONGESTION (cause ${cause})`);
  } else {
    logger.warn(`Unknown hangup cause ${cause} (${event.cause_txt}) - marking as failed`);
  }

  // Cleanup the call
  hangupCall(channel.id, disposition);
}

/**
 * Hangup call and cleanup
 */
async function hangupCall(channelId, disposition) {
  const callInfo = activeCalls.get(channelId);
  if (!callInfo) {
    logger.warn(`hangupCall called for ${channelId} but callInfo not found in activeCalls - may have been already cleaned up`);
    return;
  }

  // Clear call timeout timer if it exists
  if (callInfo.callTimeoutTimer) {
    clearTimeout(callInfo.callTimeoutTimer);
    logger.debug(`Cleared call timeout timer for channel ${channelId}`);
  }

  logger.info(`Hanging up call ${channelId} with disposition ${disposition}`);

  // Update CDR
  const duration = callInfo.answerTime ?
    Math.floor((new Date() - callInfo.startTime) / 1000) : 0;
  const billsec = callInfo.answerTime ?
    Math.floor((new Date() - callInfo.answerTime) / 1000) : 0;

  await dbPool.execute(
    `UPDATE cdr SET end_time = NOW(), duration = ?, billsec = ?, disposition = ?
     WHERE channel_id = ?`,
    [duration, billsec, disposition, channelId]
  );

  // Map disposition to campaign_numbers status
  let numberStatus;
  if (disposition === 'completed') {
    numberStatus = 'completed';
  } else if (disposition === 'answered') {
    numberStatus = 'answered';
  } else {
    // busy, no-answer, congestion, failed all map to 'failed'
    numberStatus = 'failed';
  }

  // Update campaign number status
  await dbPool.execute(
    'UPDATE campaign_numbers SET status = ? WHERE id = ?',
    [numberStatus, callInfo.numberId]
  );

  // Stop bridge recording if any
  if (callInfo.bridge) {
    const bridgeRecordings = channelRecordings.get(callInfo.bridge);
    if (bridgeRecordings && bridgeRecordings.length > 0) {
      logger.info(`Stopping ${bridgeRecordings.length} bridge recording(s) for bridge ${callInfo.bridge}`);

      for (const rec of bridgeRecordings) {
        try {
          logger.info(`ARI POST /recordings/live/${rec.recordingName}/stop - Stopping bridge recording`);

          // Stop the recording
          await ariClient.recordings.stopLiveRecording({
            recordingName: rec.recordingName
          });

          logger.info(`ARI RESPONSE: Bridge recording ${rec.recordingName} stopped successfully`);
        } catch (err) {
          logger.error(`Failed to stop bridge recording ${rec.recordingName}: ${err.message}`);
        }
      }

      channelRecordings.delete(callInfo.bridge);
    } else {
      logger.info(`No bridge recordings to stop for bridge ${callInfo.bridge}`);
    }
  } else {
    logger.info(`No bridge found for channel ${channelId}`);
  }

  // Decrement campaign current calls
  const campaign = activeCampaigns.get(callInfo.campaignId);
  if (campaign) {
    campaign.currentCalls--;
    logger.debug(`Decremented currentCalls for campaign ${callInfo.campaignId} to ${campaign.currentCalls}`);
  } else {
    // Campaign was removed/stopped while call was active - this is expected behavior
    logger.debug(`Campaign ${callInfo.campaignId} not in activeCampaigns during cleanup (may have been stopped)`);
  }

  // Remove from active calls
  activeCalls.delete(channelId);
}

/**
 * Mix two audio files into stereo MP3
 */
async function mixRecordings(callInfo) {
  try {
    const customerRec = callInfo.recordings.find(r => r.leg === 'customer');
    const agentRec = callInfo.recordings.find(r => r.leg === 'agent');

    if (!customerRec || !agentRec) {
      logger.warn(`Missing recording files for mixing - Customer: ${customerRec ? 'Yes' : 'No'}, Agent: ${agentRec ? 'Yes' : 'No'}`);
      return;
    }

    // Recording files are in /var/spool/asterisk/recording/ by default
    const recordingDir = '/var/spool/asterisk/recording';
    const customerFile = `${recordingDir}/${customerRec.file}`;
    const agentFile = `${recordingDir}/${agentRec.file}`;
    const outputFileName = `${callInfo.campaignId}-${callInfo.numberId}-${Date.now()}-mixed.wav`;
    const outputFile = `${config.recordingsPath}/${outputFileName}`;

    logger.info(`Mixing recordings:`);
    logger.info(`  Customer: ${customerFile}`);
    logger.info(`  Agent: ${agentFile}`);
    logger.info(`  Output: ${outputFile}`);

    // Check if files exist
    const fs = require('fs');
    if (!fs.existsSync(customerFile)) {
      logger.error(`Customer recording file not found: ${customerFile}`);
      return;
    }
    if (!fs.existsSync(agentFile)) {
      logger.error(`Agent recording file not found: ${agentFile}`);
      return;
    }

    // Use sox to mix files into stereo WAV
    const { exec } = require('child_process');
    const cmd = `sox -M "${customerFile}" "${agentFile}" -r 8000 -c 2 "${outputFile}"`;

    logger.info(`Executing: ${cmd}`);

    exec(cmd, async (error, stdout, stderr) => {
      if (error) {
        logger.error(`Failed to mix recordings: ${error.message}`);
        logger.error(`stderr: ${stderr}`);
        return;
      }

      logger.info(`Successfully mixed recordings to ${outputFile}`);
      if (stdout) logger.info(`sox stdout: ${stdout}`);

      // Update CDR with final recording
      await dbPool.execute(
        'UPDATE cdr SET recording_file = ? WHERE campaign_id = ? AND campaign_number_id = ?',
        [outputFileName, callInfo.campaignId, callInfo.numberId]
      );

      logger.info(`CDR updated with mixed recording: ${outputFileName}`);
    });

  } catch (err) {
    logger.error(`Error mixing recordings: ${err.message}`);
    if (err.stack) {
      logger.error(`Stack: ${err.stack}`);
    }
  }
}

// ARI client instance
let ariClient;

/**
 * Main application entry point
 */
async function main() {
  logger.info('Starting Asterisk ARI Dialer Stasis Application...');

  // Initialize database
  const dbReady = await initDatabase();
  if (!dbReady) {
    logger.error('Failed to initialize database. Exiting...');
    process.exit(1);
  }

  // Connect to ARI
  try {
    ariClient = await ari.connect(config.ari.url, config.ari.username, config.ari.password);
    logger.info('Connected to Asterisk ARI');

    // Start Stasis application
    ariClient.on('StasisStart', stasisStart);
    ariClient.on('StasisEnd', stasisEnd);
    ariClient.on('ChannelLeftBridge', channelLeftBridge);
    ariClient.on('ChannelDestroyed', channelDestroyed);

    ariClient.start(config.ari.app, (err) => {
      if (err) {
        logger.error('Failed to start Stasis application:', err);
        process.exit(1);
      }

      logger.info(`Stasis application "${config.ari.app}" started successfully`);

      // Load active campaigns
      loadActiveCampaigns();

      // Poll for campaign changes every 10 seconds
      setInterval(loadActiveCampaigns, 10000);
    });

  } catch (err) {
    logger.error('Failed to connect to ARI:', err);
    process.exit(1);
  }
}

// Handle process termination
process.on('SIGINT', () => {
  logger.info('Shutting down gracefully...');

  // Stop all campaigns
  for (const [campaignId] of activeCampaigns) {
    stopCampaign(campaignId);
  }

  // Close database pool
  if (dbPool) {
    dbPool.end();
  }

  process.exit(0);
});

// Start the application
main().catch(err => {
  logger.error('Fatal error:', err);
  process.exit(1);
});
