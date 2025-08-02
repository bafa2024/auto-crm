const express = require('express');
const router = express.Router();
const { pool } = require('../config/database');

// Get all campaigns
router.get('/', async (req, res) => {
  try {
    const [campaigns] = await pool.execute(`
      SELECT 
        id,
        name,
        subject,
        status,
        scheduled_at,
        sent_at,
        created_at
      FROM email_campaigns 
      ORDER BY created_at DESC
    `);
    
    res.json({
      success: true,
      data: campaigns
    });
  } catch (error) {
    console.error('Error fetching campaigns:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to fetch campaigns'
    });
  }
});

// Get single campaign
router.get('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const [campaigns] = await pool.execute(
      'SELECT * FROM email_campaigns WHERE id = ?',
      [id]
    );
    
    if (campaigns.length === 0) {
      return res.status(404).json({
        success: false,
        message: 'Campaign not found'
      });
    }
    
    res.json({
      success: true,
      data: campaigns[0]
    });
  } catch (error) {
    console.error('Error fetching campaign:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to fetch campaign'
    });
  }
});

// Create new campaign
router.post('/', async (req, res) => {
  try {
    const { name, subject, content, scheduled_at } = req.body;
    
    // Validation
    if (!name || !subject || !content) {
      return res.status(400).json({
        success: false,
        message: 'Name, subject, and content are required'
      });
    }
    
    const [result] = await pool.execute(
      'INSERT INTO email_campaigns (name, subject, content, scheduled_at) VALUES (?, ?, ?, ?)',
      [name, subject, content, scheduled_at]
    );
    
    const [newCampaign] = await pool.execute(
      'SELECT * FROM email_campaigns WHERE id = ?',
      [result.insertId]
    );
    
    res.status(201).json({
      success: true,
      message: 'Campaign created successfully',
      data: newCampaign[0]
    });
  } catch (error) {
    console.error('Error creating campaign:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to create campaign'
    });
  }
});

// Update campaign
router.put('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const { name, subject, content, status, scheduled_at } = req.body;
    
    const [result] = await pool.execute(
      'UPDATE email_campaigns SET name = ?, subject = ?, content = ?, status = ?, scheduled_at = ? WHERE id = ?',
      [name, subject, content, status, scheduled_at, id]
    );
    
    if (result.affectedRows === 0) {
      return res.status(404).json({
        success: false,
        message: 'Campaign not found'
      });
    }
    
    const [updatedCampaign] = await pool.execute(
      'SELECT * FROM email_campaigns WHERE id = ?',
      [id]
    );
    
    res.json({
      success: true,
      message: 'Campaign updated successfully',
      data: updatedCampaign[0]
    });
  } catch (error) {
    console.error('Error updating campaign:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to update campaign'
    });
  }
});

// Delete campaign
router.delete('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    
    const [result] = await pool.execute(
      'DELETE FROM email_campaigns WHERE id = ?',
      [id]
    );
    
    if (result.affectedRows === 0) {
      return res.status(404).json({
        success: false,
        message: 'Campaign not found'
      });
    }
    
    res.json({
      success: true,
      message: 'Campaign deleted successfully'
    });
  } catch (error) {
    console.error('Error deleting campaign:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to delete campaign'
    });
  }
});

// Send campaign
router.post('/:id/send', async (req, res) => {
  try {
    const { id } = req.params;
    
    // Update campaign status to 'sending'
    await pool.execute(
      'UPDATE email_campaigns SET status = ?, sent_at = NOW() WHERE id = ?',
      ['sent', id]
    );
    
    res.json({
      success: true,
      message: 'Campaign sent successfully'
    });
  } catch (error) {
    console.error('Error sending campaign:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to send campaign'
    });
  }
});

module.exports = router; 