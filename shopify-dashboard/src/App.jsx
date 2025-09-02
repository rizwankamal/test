import { Page, Layout, Card, BlockStack, InlineStack, Button, Icon, Text, DataTable } from '@shopify/polaris'
import { Routes, Route, Link } from 'react-router-dom'
import {
  InventoryMajor,
  OrdersMajor,
  AnalyticsMajor,
  MarketingMajor,
  DataVisualizationMajor,
  RefreshMajor,
} from '@shopify/polaris-icons'

function Dashboard() {
  const inventoryRows = [
    ['SKU-001', 'Product A', '120', '$10,500'],
    ['SKU-002', 'Product B', '45', '$3,200'],
  ]
  const salesRows = [
    ['Today', '$1,230', '24 orders'],
    ['7 days', '$9,845', '176 orders'],
  ]

  return (
    <Page title="Dashboard" subtitle="Store overview">
      <Layout>
        <Layout.Section>
          <BlockStack gap="400">
            <InlineStack gap="400" wrap>
              <Card>
                <BlockStack gap="200" padding="400">
                  <InlineStack align="space-between">
                    <InlineStack gap="200" align="start">
                      <Icon source={InventoryMajor} tone="base" />
                      <Text as="h3" variant="headingSm">Inventory</Text>
                    </InlineStack>
                    <Button variant="secondary" icon={RefreshMajor}>Sync inventory</Button>
                  </InlineStack>
                  <DataTable
                    columnContentTypes={["text","text","numeric","text"]}
                    headings={["SKU","Product","Qty","Value"]}
                    rows={inventoryRows}
                  />
                </BlockStack>
              </Card>

              <Card>
                <BlockStack gap="200" padding="400">
                  <InlineStack align="space-between">
                    <InlineStack gap="200">
                      <Icon source={OrdersMajor} tone="base" />
                      <Text as="h3" variant="headingSm">Sales Data</Text>
                    </InlineStack>
                    <Button variant="secondary" icon={RefreshMajor}>Sync sales</Button>
                  </InlineStack>
                  <DataTable
                    columnContentTypes={["text","text","text"]}
                    headings={["Range","Revenue","Orders"]}
                    rows={salesRows}
                  />
                </BlockStack>
              </Card>
            </InlineStack>

            <InlineStack gap="400" wrap>
              <Card>
                <BlockStack gap="200" padding="400">
                  <InlineStack gap="200">
                    <Icon source={AnalyticsMajor} tone="base" />
                    <Text as="h3" variant="headingSm">Crux Data</Text>
                  </InlineStack>
                  <Text tone="subdued">Coming soon</Text>
                </BlockStack>
              </Card>

              <Card>
                <BlockStack gap="200" padding="400">
                  <InlineStack gap="200">
                    <Icon source={DataVisualizationMajor} tone="base" />
                    <Text as="h3" variant="headingSm">Economic Data</Text>
                  </InlineStack>
                  <Text tone="subdued">Coming soon</Text>
                </BlockStack>
              </Card>

              <Card>
                <BlockStack gap="200" padding="400">
                  <InlineStack gap="200">
                    <Icon source={MarketingMajor} tone="base" />
                    <Text as="h3" variant="headingSm">Google Ads Data</Text>
                  </InlineStack>
                  <Text tone="subdued">Coming soon</Text>
                </BlockStack>
              </Card>
            </InlineStack>

            <InlineStack gap="400" wrap>
              <Card>
                <BlockStack gap="200" padding="400">
                  <InlineStack gap="200">
                    <Icon source={AnalyticsMajor} tone="base" />
                    <Text as="h3" variant="headingSm">Google Console Data</Text>
                  </InlineStack>
                  <Text tone="subdued">Coming soon</Text>
                </BlockStack>
              </Card>

              <Card>
                <BlockStack gap="200" padding="400">
                  <InlineStack gap="200">
                    <Icon source={MarketingMajor} tone="base" />
                    <Text as="h3" variant="headingSm">Klaviyo Data</Text>
                  </InlineStack>
                  <Text tone="subdued">Coming soon</Text>
                </BlockStack>
              </Card>
            </InlineStack>
          </BlockStack>
        </Layout.Section>
      </Layout>
    </Page>
  )
}

export default function App() {
  return (
    <Routes>
      <Route path="/" element={<Dashboard />} />
      <Route path="*" element={<NotFound />} />
    </Routes>
  )
}

function NotFound() {
  return (
    <Page title="Not found">
      <Card>
        <BlockStack padding="400" gap="200">
          <Text>Go to <Link to="/">Dashboard</Link></Text>
        </BlockStack>
      </Card>
    </Page>
  )
}
