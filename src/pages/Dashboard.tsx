import React from 'react';
import Layout from '../components/Layout/Layout';

const Dashboard: React.FC = () => {
  const stats = [
    {
      title: 'Total Contacts',
      value: '1,234',
      change: '+12%',
      changeType: 'positive',
      icon: '👥'
    },
    {
      title: 'Active Campaigns',
      value: '8',
      change: '+2',
      changeType: 'positive',
      icon: '📧'
    },
    {
      title: 'Emails Sent',
      value: '45,678',
      change: '+8%',
      changeType: 'positive',
      icon: '📤'
    },
    {
      title: 'Open Rate',
      value: '23.4%',
      change: '-2.1%',
      changeType: 'negative',
      icon: '📊'
    }
  ];

  const recentActivities = [
    {
      id: 1,
      type: 'contact_added',
      message: 'New contact "John Doe" was added',
      time: '2 minutes ago',
      icon: '👤'
    },
    {
      id: 2,
      type: 'campaign_sent',
      message: 'Campaign "Summer Sale" was sent to 1,234 recipients',
      time: '1 hour ago',
      icon: '📧'
    },
    {
      id: 3,
      type: 'email_opened',
      message: 'High open rate for "Newsletter" campaign',
      time: '3 hours ago',
      icon: '📈'
    },
    {
      id: 4,
      type: 'contact_updated',
      message: 'Contact "Jane Smith" was updated',
      time: '5 hours ago',
      icon: '✏️'
    }
  ];

  return (
    <Layout>
      <div className="space-y-6">
        {/* Page Header */}
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
          <p className="text-gray-600">Welcome to your ACRM dashboard</p>
        </div>

        {/* Stats Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {stats.map((stat, index) => (
            <div key={index} className="card">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-600">{stat.title}</p>
                  <p className="text-2xl font-bold text-gray-900">{stat.value}</p>
                  <p className={`text-sm ${
                    stat.changeType === 'positive' ? 'text-green-600' : 'text-red-600'
                  }`}>
                    {stat.change} from last month
                  </p>
                </div>
                <div className="text-3xl">{stat.icon}</div>
              </div>
            </div>
          ))}
        </div>

        {/* Charts and Analytics */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Email Campaign Performance */}
          <div className="card">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">
              Email Campaign Performance
            </h3>
            <div className="space-y-4">
              <div className="flex justify-between items-center">
                <span className="text-sm text-gray-600">Campaign Name</span>
                <span className="text-sm text-gray-600">Open Rate</span>
              </div>
              <div className="space-y-2">
                <div className="flex justify-between items-center">
                  <span className="text-sm">Summer Sale</span>
                  <span className="text-sm font-medium">24.5%</span>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-2">
                  <div className="bg-blue-600 h-2 rounded-full" style={{ width: '24.5%' }}></div>
                </div>
              </div>
              <div className="space-y-2">
                <div className="flex justify-between items-center">
                  <span className="text-sm">Newsletter</span>
                  <span className="text-sm font-medium">18.2%</span>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-2">
                  <div className="bg-green-600 h-2 rounded-full" style={{ width: '18.2%' }}></div>
                </div>
              </div>
              <div className="space-y-2">
                <div className="flex justify-between items-center">
                  <span className="text-sm">Product Launch</span>
                  <span className="text-sm font-medium">32.1%</span>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-2">
                  <div className="bg-purple-600 h-2 rounded-full" style={{ width: '32.1%' }}></div>
                </div>
              </div>
            </div>
          </div>

          {/* Recent Activity */}
          <div className="card">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">
              Recent Activity
            </h3>
            <div className="space-y-4">
              {recentActivities.map((activity) => (
                <div key={activity.id} className="flex items-start space-x-3">
                  <div className="flex-shrink-0 w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                    <span className="text-sm">{activity.icon}</span>
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm text-gray-900">{activity.message}</p>
                    <p className="text-xs text-gray-500">{activity.time}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Quick Actions */}
        <div className="card">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">
            Quick Actions
          </h3>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <button className="flex items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-colors duration-200">
              <div className="text-center">
                <div className="text-2xl mb-2">👥</div>
                <div className="text-sm font-medium text-gray-900">Add Contact</div>
              </div>
            </button>
            <button className="flex items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-colors duration-200">
              <div className="text-center">
                <div className="text-2xl mb-2">📧</div>
                <div className="text-sm font-medium text-gray-900">Create Campaign</div>
              </div>
            </button>
            <button className="flex items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-colors duration-200">
              <div className="text-center">
                <div className="text-2xl mb-2">📊</div>
                <div className="text-sm font-medium text-gray-900">View Reports</div>
              </div>
            </button>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default Dashboard; 